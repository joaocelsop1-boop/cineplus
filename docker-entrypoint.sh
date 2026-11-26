#!/bin/bash
# StreamFlix — entrypoint do container app.
# 1) espera o MySQL; 2) importa o seed se o banco estiver vazio;
# 3) escreve config/database.php a partir das variaveis de ambiente;
# 4) cria/atualiza o admin (hash do proprio sistema: md5(md5($senha)));
# 5) grava o token do Mercado Pago em site_perfil, se informado;
# 6) garante cache/ gravavel e inicia o Apache.
set -e

DB_HOST="${DB_HOST:-db}"
DB_NAME="${DB_NAME:-streamflix}"
DB_USER="${DB_USER:-streamflix}"
DB_PASS="${DB_PASS:-streamflix}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@streamflix.local}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-trocar_esta_senha}"
export DB_HOST DB_NAME DB_USER DB_PASS ADMIN_EMAIL ADMIN_PASSWORD

echo "[entrypoint] Aguardando MySQL em ${DB_HOST}..."
for i in $(seq 1 60); do
  if MYSQL_PWD="$DB_PASS" mysql --skip-ssl -h"$DB_HOST" -u"$DB_USER" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
    echo "[entrypoint] MySQL OK."
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "[entrypoint] ERRO: MySQL nao respondeu em 60s." >&2
    exit 1
  fi
  sleep 2
done

# Importar o seed so se o banco estiver vazio (sem tabelas).
TABELAS=$(MYSQL_PWD="$DB_PASS" mysql --skip-ssl -h"$DB_HOST" -u"$DB_USER" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
if [ "$TABELAS" -eq 0 ]; then
  echo "[entrypoint] Banco vazio: importando banco de dados.sql..."
  MYSQL_PWD="$DB_PASS" mysql --skip-ssl -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" < "/var/www/html/banco de dados.sql"
  echo "[entrypoint] Seed importado."
else
  echo "[entrypoint] Banco ja tem $TABELAS tabelas, pulando o seed."
fi

# config/database.php a partir das variaveis de ambiente.
cat > /var/www/html/config/database.php <<DBPHP
<?php 
#informacoes banco de dados (gerado pelo docker-entrypoint.sh a partir das
#variaveis de ambiente; nao edite a mao dentro do container)
define("DB_HOST", "${DB_HOST}");
define("DB_NAME", "${DB_NAME}");
define("DB_USER", "${DB_USER}");
define("DB_PASS", "${DB_PASS}");
define("DB_CON", "mysql:dbname=" . DB_NAME . ";host=" . DB_HOST . ";charset=utf8");
DBPHP

# Admin + token do Mercado Pago, com o hash do proprio sistema
# (model/system/hash.php: md5(md5($senha))). O hash e feito em PHP, nunca no shell.
php <<'PHP'
<?php
require '/var/www/html/model/system/hash.php';
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'streamflix';
$user = getenv('DB_USER') ?: 'streamflix';
$pass = getenv('DB_PASS') ?: 'streamflix';
$pdo = new PDO("mysql:dbname={$db};host={$host};charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$admin_email = getenv('ADMIN_EMAIL') ?: 'admin@streamflix.local';
$admin_pass  = getenv('ADMIN_PASSWORD') ?: 'trocar_esta_senha';
$hash = hash_senha($admin_pass);
$st = $pdo->prepare("SELECT user_id FROM `user` WHERE user_email = ?");
$st->execute([$admin_email]);
if ($st->fetch()) {
    $pdo->prepare("UPDATE `user` SET user_senha = ?, user_tipo = 'admin' WHERE user_email = ?")->execute([$hash, $admin_email]);
    echo "[entrypoint] Admin atualizado: {$admin_email}
";
} else {
    $pdo->prepare("INSERT INTO `user` (user_email, user_senha, user_nome, user_revendedor, user_tipo) VALUES (?, ?, 'Admin', 0, 'admin')")->execute([$admin_email, $hash]);
    echo "[entrypoint] Admin criado: {$admin_email}
";
}

$mp = getenv('MP_ACCESS_TOKEN');
if (!empty($mp)) {
    $pdo->prepare("UPDATE site_perfil SET site_token_mp = ? WHERE site_perfil_id = 1")->execute([$mp]);
    echo "[entrypoint] Token do Mercado Pago gravado em site_perfil.
";
} else {
    echo "[entrypoint] MP_ACCESS_TOKEN vazio: token do Mercado Pago nao alterado (configure no admin).
";
}
PHP

# cache/ gravavel (o catalogo do painel e salvo em cache/stream.php).
mkdir -p /var/www/html/cache
chown -R www-data:www-data /var/www/html/cache
chmod 775 /var/www/html/cache

echo "[entrypoint] Pronto. Iniciando o Apache..."
exec "$@"
