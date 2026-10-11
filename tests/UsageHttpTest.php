<?php
declare(strict_types=1);

/** Run against the disposable database created by UsageStatisticsTest. */
function usage_http_checks(PDO $db, string $name, string $dsn): void
{
    $db->exec(file_get_contents(dirname(__DIR__) . '/database/seed.sql'));
    $adminPassword = bin2hex(random_bytes(12));
    $stmt = $db->prepare('INSERT INTO admins (email,name,password_hash,active,created_at) VALUES (?,?,?,1,NOW())');
    $stmt->execute(['stats-test@example.invalid','Stats test',password_hash($adminPassword, PASSWORD_DEFAULT)]);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Não foi possível reservar porta de teste.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    preg_match('/(?:^|;)host=([^;]+)/', substr($dsn, 6), $host);
    preg_match('/(?:^|;)port=([^;]+)/', substr($dsn, 6), $port);
    $env = array_merge(getenv(), [
        'DB_HOST'=>$host[1] ?? '127.0.0.1','DB_PORT'=>$port[1] ?? '3306',
        'DB_NAME'=>$name,'DB_USER'=>getenv('QUALPACOTE_TEST_USER') ?: 'root',
        'DB_PASS'=>getenv('QUALPACOTE_TEST_PASSWORD') ?: '',
        'APP_HTTPS'=>'false','APP_TIMEZONE'=>'Africa/Luanda',
        'APP_SESSION_NAME'=>'qualpacote_http_test_' . bin2hex(random_bytes(6)),
    ]);
    $sessionDirectory = sys_get_temp_dir() . '/qualpacote_http_' . bin2hex(random_bytes(6));
    mkdir($sessionDirectory, 0700);
    $log = tmpfile();
    $process = proc_open([getenv('QUALPACOTE_TEST_PHP') ?: PHP_BINARY, '-d', 'session.save_path=' . $sessionDirectory, '-S', $address, '-t', dirname(__DIR__)], [0=>['pipe','r'],1=>$log,2=>$log], $pipes, null, $env);
    if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar PHP de teste.');
    fclose($pipes[0]);
    $base = 'http://' . $address;
    $request = static function (string $path, array &$cookies, ?array $data = null, bool $json = false) use ($base): array {
        $headers = ['Cookie: ' . implode('; ', array_map(static fn ($key,$value)=>$key.'='.$value, array_keys($cookies), $cookies))];
        if ($data !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        if ($json) $headers[] = 'Accept: application/json';
        $context = stream_context_create(['http'=>[
            'method'=>$data === null ? 'GET' : 'POST','header'=>implode("\r\n",$headers),
            'content'=>$data === null ? '' : http_build_query($data),
            'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10,
        ]]);
        $body = file_get_contents($base . $path, false, $context);
        $responseHeaders = $http_response_header ?? [];
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $match)) $cookies[$match[1]] = $match[2];
        }
        preg_match('/^HTTP\/\S+ (\d+)/', $responseHeaders[0] ?? '', $match);
        return ['status'=>(int) ($match[1] ?? 0),'body'=>$body ?: '','headers'=>$responseHeaders];
    };
    $field = static function (string $html, string $field): string {
        preg_match('/name="'.preg_quote($field,'/').'" value="([^"]*)"/', $html, $match);
        usage_expect(isset($match[1]), 'Campo de formulário ausente: '.$field);
        return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    };
    try {
        for ($i=0;$i<100;$i++) {
            $ready = @stream_socket_client('tcp://'.$address, $errno, $error, .1);
            if ($ready) { fclose($ready); break; }
            usleep(20000);
        }
        $public = [];
        $home = $request('/', $public);
        usage_expect($home['status'] === 200 && isset($public['qualpacote_visitor']), 'Página pública cria cookie anónimo.');
        usage_expect((bool) array_filter($home['headers'], static fn ($h)=>strtolower($h)==='cache-control: no-store'), 'Tokens públicos não podem ser guardados em cache.');
        $query = '/?' . http_build_query(['compare'=>1,'consultation'=>$field($home['body'],'consultation'),'budget_kz'=>2000,'services'=>['voice'],'duration_days'=>7]);
        $before = (int) $db->query('SELECT COUNT(*) FROM search_events')->fetchColumn();
        $result = $request($query, $public);
        usage_expect($result['status'] === 200 && str_contains($result['body'], 'data-feedback-form'), 'Cada recomendação tem botões reais de voto.');
        usage_expect(!str_contains($result['body'], 'de aprovação entre os votos'), 'Não há agregados administrativos no público.');
        $request($query, $public);
        usage_expect((int) $db->query('SELECT COUNT(*) FROM search_events')->fetchColumn() === $before+1, 'Actualizar a página não duplica consultas.');
        $vote = ['_csrf'=>$field($result['body'],'_csrf'),'result'=>$field($result['body'],'result'),'vote'=>'1'];
        usage_expect($request('/feedback.php', $public)['status'] === 405, 'Votos só aceitam POST.');
        usage_expect($request('/feedback.php', $public, array_merge($vote,['_csrf'=>'invalid']), true)['status'] === 403, 'CSRF inválido rejeitado via HTTP.');
        usage_expect($request('/feedback.php', $public, array_merge($vote,['vote'=>'1x']), true)['status'] === 422, 'Voto malformado rejeitado.');
        $voted = $request('/feedback.php', $public, $vote, true);
        usage_expect($voted['status'] === 200 && array_keys(json_decode($voted['body'], true)) === ['ok','message'], 'Endpoint público devolve só confirmação, sem estatísticas.');
        $request('/feedback.php', $public, $vote, true);
        $row = $db->query("SELECT vote FROM usage_results WHERE vote_token='".$vote['result']."'")->fetch();
        usage_expect((int) $row['vote'] === 1, 'Repetir voto via HTTP mantém opinião.');
        $other = [];
        $otherLogin = $request('/admin/?page=statistics', $other);
        usage_expect(str_contains($otherLogin['body'], 'name="action" value="login"') && !str_contains($otherLogin['body'], 'Últimas opiniões'), 'Estatísticas exigem login.');
        $request('/', $other);
        $otherVote = array_merge($vote, ['_csrf'=>$field($otherLogin['body'],'_csrf')]);
        usage_expect($request('/feedback.php', $other, $otherVote, true)['status'] === 422, 'Outra sessão não pode votar num resultado alheio.');
        $fallback = $request('/feedback.php', $public, array_merge($vote,['vote'=>'-1']));
        usage_expect($fallback['status'] === 303, 'Sem JS, votar redirecciona para resultados.');
        $redirect = array_values(array_filter($fallback['headers'], static fn ($h)=>str_starts_with($h,'Location: ')))[0];
        $request(substr($redirect,10), $public);
        usage_expect((int) $db->query('SELECT COUNT(*) FROM search_events')->fetchColumn() === $before+1, 'Redireccionar após voto não conta nova consulta.');
        $next = '/?' . http_build_query(['compare'=>1,'consultation'=>$field($result['body'],'consultation'),'budget_kz'=>2000,'services'=>['voice'],'duration_days'=>7]);
        $request($next, $public);
        usage_expect((int) $db->query('SELECT COUNT(*) FROM search_events')->fetchColumn() === $before+2, 'Novo clique de consulta conta de novo.');
        $login = $request('/admin/', $other, ['action'=>'login','_csrf'=>$field($otherLogin['body'],'_csrf'),'email'=>'stats-test@example.invalid','password'=>$adminPassword]);
        usage_expect($login['status'] === 302, 'Login administrativo válido.');
        $admin = $request('/admin/?page=statistics', $other);
        usage_expect($admin['status'] === 200 && str_contains($admin['body'], 'Últimas opiniões') && !str_contains($admin['body'], 'Estatísticas indisponíveis'), 'Admin lê métricas reais.');
        $bad = $request('/admin/?page=statistics&from=2026-02-30&to=2026-03-01', $other);
        usage_expect(str_contains($bad['body'], 'Escolha datas válidas'), 'Filtro inválido apresenta erro compreensível.');
        foreach (['usage_results','usage_consultations','usage_visit_days','usage_visitors'] as $table) $db->exec("DROP TABLE $table");
        $missing = $request('/?compare=1&budget_kz=2000&services[]=voice', $public);
        usage_expect($missing['status'] === 200 && str_contains($missing['body'],'result-card'), 'Migração pendente não interrompe recomendações.');
        $admin = $request('/admin/?page=statistics', $other);
        usage_expect(str_contains($admin['body'], '20261011_usage_statistics.sql'), 'Admin explica migração pendente.');
        fwrite(STDOUT, "OK: UsageHttpTest (público, votação, autenticação e migração pendente)\n");
    } catch (Throwable $e) {
        rewind($log);
        fwrite(STDERR, stream_get_contents($log));
        throw $e;
    } finally {
        proc_terminate($process);
        proc_close($process);
        fclose($log);
        foreach (glob($sessionDirectory . '/*') as $file) unlink($file);
        rmdir($sessionDirectory);
    }
}
