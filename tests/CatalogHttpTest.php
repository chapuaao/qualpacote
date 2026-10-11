<?php
declare(strict_types=1);
function catalog_http_checks(PDO $db, string $name, string $dsn, int $adminId, int $snapshotId): void
{
    $password=bin2hex(random_bytes(12));$db->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$adminId]);
    $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
    preg_match('/(?:^|;)host=([^;]+)/',substr($dsn,6),$host);preg_match('/(?:^|;)port=([^;]+)/',substr($dsn,6),$port);
    $env=array_merge(getenv(),['DB_HOST'=>$host[1] ?? '127.0.0.1','DB_PORT'=>$port[1] ?? '3306','DB_NAME'=>$name,'DB_USER'=>getenv('QUALPACOTE_TEST_USER') ?: 'root','DB_PASS'=>getenv('QUALPACOTE_TEST_PASSWORD') ?: '','APP_HTTPS'=>'false','APP_TIMEZONE'=>'Africa/Luanda','APP_SESSION_NAME'=>'qualpacote_catalog_http_'.bin2hex(random_bytes(5))]);
    $session=sys_get_temp_dir().'/qualpacote_catalog_http_'.bin2hex(random_bytes(5));mkdir($session,0700);$log=tmpfile();
    $process=proc_open([getenv('QUALPACOTE_TEST_PHP') ?: PHP_BINARY,'-d','session.save_path='.$session,'-S',$address,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>$log,2=>$log],$pipes,null,$env);fclose($pipes[0]);
    $request=static function(string $path,array &$cookies,?array $data=null) use($address):array {
        $headers=['Cookie: '.implode('; ',array_map(static fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies))];if($data!==null)$headers[]='Content-Type: application/x-www-form-urlencoded';
        $context=stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
        $body=file_get_contents('http://'.$address.$path,false,$context);$response=$http_response_header ?? [];
        foreach($response as $h)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$h,$m))$cookies[$m[1]]=$m[2];preg_match('/^HTTP\/\S+ (\d+)/',$response[0] ?? '',$m);
        return ['status'=>(int)($m[1] ?? 0),'body'=>$body ?: '','headers'=>$response];
    };
    $field=static function(string $html,string $name):string { preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$html,$m);catalog_expect(isset($m[1]),'Formulário sem campo '.$name);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); };
    try {
        for($i=0;$i<100;$i++){ $ready=@stream_socket_client('tcp://'.$address,$errno,$error,.1);if($ready){fclose($ready);break;}usleep(20000); }
        $cookies=[];$login=$request('/admin/?page=catalog',$cookies);
        catalog_expect($login['status']===200 && str_contains($login['body'],'value="login"'),'Fila de revisão protegida por login');
        catalog_expect($request('/admin/evidence.php?id='.$snapshotId,$cookies)['status']===403,'Evidência privada exige autenticação');
        catalog_expect($request('/admin/catalog.php',$cookies)['status']===404,'Fragmento administrativo não é endpoint público');
        $public=$request('/catalog.php',$cookies);
        catalog_expect($public['status']===200 && str_contains($public['body'],'Movinet') && !str_contains($public['body'],'Konekta 4G 6 GB'),'Catálogo móvel mostra ofertas sem misturar router');
        $routers=$request('/catalog.php?category=router',$cookies);
        catalog_expect(str_contains($routers['body'],'Konekta 4G 6 GB') && !str_contains($routers['body'],'FLEX 25'),'Filtro de router fica separado');
        $loginResult=$request('/admin/',$cookies,['action'=>'login','_csrf'=>$field($login['body'],'_csrf'),'email'=>'catalog-test@example.test','password'=>$password]);
        catalog_expect($loginResult['status']===302,'Login administrativo');
        $list=$request('/admin/?page=catalog',$cookies);catalog_expect($list['status']===200 && str_contains($list['body'],'Propostas pendentes'),'Admin mostra fila de trabalho');
        $row=$db->query("SELECT id FROM catalog_proposals WHERE name='Afrimix 1D 200' AND state='pending' ORDER BY snapshot_id DESC,id DESC LIMIT 1")->fetch();$id=(int)$row['id'];
        $review=$request('/admin/?page=catalog-review&id='.$id,$cookies);catalog_expect(str_contains($review['body'],'Aprovar e publicar nova versão') && str_contains($review['body'],'Evidência recolhida'),'Revisão apresenta diferença e evidência');
        $edit=$request('/admin/?page=catalog-edit&id='.$id,$cookies);catalog_expect(str_contains($edit['body'],'value="save_catalog_draft"') && str_contains($edit['body'],'rules[repeat_policy]') && str_contains($edit['body'],'name="catalog_plan_id"'),'Editor prepara rascunho com regras e identidade por oferta, sem publicar');
        $csrf=$field($review['body'],'_csrf');$approve=['action'=>'approve_catalog','id'=>$id,'confirmed'=>1,'_csrf'=>'wrong'];
        $bad=$request('/admin/?page=catalog',$cookies,$approve);catalog_expect(str_contains($bad['body'],'sessão expirou'),'CSRF inválido bloqueia publicação');
        catalog_expect($db->query('SELECT state FROM catalog_proposals WHERE id='.$id)->fetchColumn()==='pending','CSRF não modifica proposta');
        $approve['_csrf']=$csrf;$approve['confirmed']=0;$bad=$request('/admin/?page=catalog',$cookies,$approve);
        catalog_expect(str_contains($bad['body'],'Confirme que reviu'),'Publicação requer revisão explícita');
        $approve['confirmed']=1;$approved=$request('/admin/?page=catalog',$cookies,$approve);
        catalog_expect($approved['status']===200 && str_contains($approved['body'],'Proposta publicada numa nova versão') && $db->query('SELECT state FROM catalog_proposals WHERE id='.$id)->fetchColumn()==='approved','Fluxo HTTP publica só depois da revisão');
        $document=$request('/admin/evidence.php?id='.$snapshotId,$cookies);catalog_expect($document['status']===200 && str_starts_with($document['body'],'%PDF'),'Admin pode consultar o documento original');
        catalog_expect($request('/scripts/check_sources.php',$cookies)['status']===404,'Rotina não pode ser disparada pela web');
        $mobile=$request('/?compare=1&budget_kz=1000&services[]=data&duration_days=7',$cookies);
        catalog_expect($mobile['status']===200 && str_contains($mobile['body'],'data-feedback-form') && str_contains($mobile['body'],'Como comprar'),'Compras e feedback funcionam com catálogo expandido');
        catalog_expect(!str_contains($mobile['body'],'Catálogo revisto até'),'Data mais recente não é apresentada como revisão de todo o catálogo');
        catalog_expect(strpos($mobile['body'],'Quais redes preferes?') < strpos($mobile['body'],'Plano já activo durante todo o período?') && strpos($mobile['body'],'name="router_ready"') > strpos($mobile['body'],'id="call_scope"'),'Redes são o primeiro filtro; equipamento é o último');
        catalog_expect(!str_contains($mobile['body'],'Vantagem sobre saldo normal') && !str_contains($mobile['body'],'class="result-summary"'),'Resumo genérico e equivalência repetida foram removidos');
        catalog_expect(str_contains($mobile['body'],'<details class="result-details"><summary>Condições e limites</summary>'),'Restrições conservadas em detalhes recolhidos');
        catalog_expect(str_contains($mobile['body'],'data-router-ready-field hidden') && str_contains($mobile['body'],'data-sms-scope-field hidden'),'Campos irrelevantes ficam recolhidos no telemóvel sem SMS');
        $africell=(int)$db->query("SELECT id FROM operators WHERE slug='africell'")->fetchColumn();
        $simple=$request('/?compare=1&budget_kz=300&services[]=data&services[]=voice&duration_days=3&call_scope=same&operators[]='.$africell,$cookies);
        preg_match_all('/<article class="result-card[^\"]*">(.*?)<\/article>/s',$simple['body'],$cards);
        $afrimix=array_values(array_filter($cards[1],static fn($card)=>str_contains($card,'<h3>Afrimix 3D 300</h3>')));
        catalog_expect(count($afrimix)===1 && substr_count($afrimix[0],'Activar:')===1 && substr_count($afrimix[0],'Verificado em')===1 && !str_contains($afrimix[0],'Como comprar'),'Pacote de uma compra apresenta activação e data uma única vez');
        $router=$request('/?compare=1&budget_kz=4000&services[]=data&duration_days=7&category=router',$cookies);
        catalog_expect(str_contains($router['body'],'essential-conditions') && str_contains($router['body'],'Requer SIM e MiFi/router'),'Condição de equipamento permanece visível, fora dos detalhes');
        fwrite(STDOUT,"OK: CatalogHttpTest (revisão, CSRF, evidência e categorias)\n");
    } catch(Throwable $e){rewind($log);fwrite(STDERR,stream_get_contents($log));throw $e;}
    finally{proc_terminate($process);proc_close($process);fclose($log);foreach(glob($session.'/*') as $file)unlink($file);rmdir($session);}
}
