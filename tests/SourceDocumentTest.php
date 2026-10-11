<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/SourceFetcher.php';
require_once dirname(__DIR__) . '/app/SourceDocument.php';
use QualPacote\SourceFetcher;
use QualPacote\SourceDocument;
function source_expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$body = file_get_contents(__DIR__ . '/fixtures/afrimix.html');
$text = SourceDocument::text($body); $offers = SourceDocument::extract('afrimix',$text);
source_expect(count($offers) === 4 && $offers[1]['price_kz'] === 300.0 && $offers[1]['activation_code'] === '*123*7*2#', 'Extracção de campos Afrimix');
source_expect($offers[3]['benefits'][0]['quantity'] === 1280.0 && $offers[3]['benefits'][1]['network_scope'] === 'ONNET', 'Conversão de GB e rede restrita');
source_expect(SourceDocument::text(str_replace('rodapé sem condições tarifárias','rodapé alterado',$body)) === $text, 'Rodapé não cria alteração tarifária');
source_expect(SourceDocument::text(str_replace('Kz 300','Kz 350',$body)) !== $text, 'Preço deve criar diferença');
source_expect(SourceDocument::extract('afrimix','layout alterado sem dados') === [], 'Layout desconhecido não inventa plano');
$movinet = SourceDocument::extract('movinet',"100MB\n100KZ/DIA\n*200*100#\nAntes: 200MB\nAGORA\n400MB\n350KZ/7 DIAS\n*200*350#\n15GB\n10.000KZ/30DIAS\n*200*10000#");
source_expect(count($movinet) === 3 && $movinet[1]['benefits'][0]['quantity'] === 400.0 && $movinet[2]['price_kz'] === 10000.0, 'Não importar volumes antigos rasurados; preços com milhares');
$noCode = SourceDocument::extract('movinet',"100MB\n100KZ/DIA");
source_expect($noCode[0]['activation_code'] === '', 'Não inferir códigos ausentes');
$konekta = SourceDocument::extract('konekta4g',"Konekta 6 GB Kz 4,000 6 GB 7 dias\nKonekta 25 GB Kz 13,000 25 GB 30 dias");
source_expect(count($konekta) === 2 && $konekta[0]['rules']['category'] === 'router', 'Separar categoria e não inventar quota extra');
source_expect($konekta[0]['activation_code'] === '', 'Não inventar activação Konekta quando ausente da evidência');
foreach (['file:///etc/passwd','http://127.0.0.1/','https://africell.ao.evil.test/','https://user:password@africell.ao/','https://www.africell.ao:8443/','https://evil.test/','ftp://africell.ao/'] as $url) source_expect(!SourceFetcher::allowed($url), 'Bloquear fonte inválida: ' . $url);
source_expect(SourceFetcher::allowed('https://www.africell.ao/afrimix/'), 'Permitir fonte oficial');
source_expect(SourceDocument::resolve('https://www.movicel.co.ao/plano/FAQ/Movinet.html','../movinet.html') === 'https://www.movicel.co.ao/plano/movinet.html', 'Resolver referências relativas');
source_expect(SourceDocument::resolve('https://www.africell.ao/afrimix/','?page=2') === 'https://www.africell.ao/afrimix/?page=2', 'Parâmetro relativo mantém a página');
source_expect(SourceDocument::resolve('https://www.africell.ao/','file:///etc/passwd') === null, 'Não transformar um esquema inválido numa ligação oficial');
$links = SourceDocument::links('<a href="/afrimix/">Pacote</a><a href="https://evil.test/plan">Plano</a><a href="#plan">Âncora</a><a href="/afrimix/">Repetido</a>', 'https://www.africell.ao/');
source_expect(count($links) === 1, 'Descoberta limitada a fontes oficiais da mesma origem, sem duplicados');
fwrite(STDOUT,"OK: SourceDocumentTest\n");
