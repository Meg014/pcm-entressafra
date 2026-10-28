<?php
declare(strict_types=1);
/** Isolated Apache configuration; does not include or change XAMPP's running service. */
function apachePcmConfig(string $webroot,string $runtime,int $port): string
{
    $webroot=str_replace('\\','/',$webroot);$runtime=str_replace('\\','/',$runtime);
    foreach([$webroot,$runtime] as $path)if(str_contains($path,'"')||str_contains($path,"\n"))throw new RuntimeException('Caminho inválido.');
    return <<<CONF
ServerRoot "C:/xampp/apache"
ServerName 127.0.0.1
Listen 127.0.0.1:$port
PidFile "$runtime.pid"
ErrorLog "$runtime-error.log"
LogLevel warn
ThreadsPerChild 32
MaxConnectionsPerChild 0
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
TypesConfig conf/mime.types
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
<FilesMatch "\\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
DocumentRoot "$webroot"
DirectoryIndex index.php
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$webroot">
    Require all granted
    AllowOverride None
    Options -Indexes
    FallbackResource /index.php
</Directory>

CONF;
}
