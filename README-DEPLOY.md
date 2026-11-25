# StreamFlix — deploy no Coolify (Oracle)

## O que é
Site de streaming estilo Netflix/MineTV (PHP + MySQL), com catálogo vindo de um
painel IPTV Xtream, compra via Mercado Pago e player com proxy HTTPS (o painel
só fala HTTP, então o vídeo passa pelo próprio site — sem mixed content e sem
expor usuário/senha do painel no navegador).

## Subir no Coolify
1. Suba este pacote para um repositório git (privado de preferência).
2. No Coolify: **Novo recurso → Docker Compose** a partir desse repositório.
3. Em **Variáveis de ambiente** do app, preencha (veja `.env.example`):
   - `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_ROOT_PASS` — banco MySQL
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD` — login do admin (`/painel-admin`)
   - `MP_ACCESS_TOKEN` — token do Mercado Pago (opcional; pode por depois no admin)
   - `SITE_URL` — URL pública do site, ex.: `https://streamflix.seudominio.com` (opcional)
4. Deploy. Na primeira subida o entrypoint importa o seed (`banco de dados.sql`),
   cria o admin e grava o token do Mercado Pago, se informado.

## Depois do deploy (no admin, `/painel-admin`)
1. **Servidor IPTV**: cadastre o painel Xtream (host, usuário e senha). Sem ele o
   catálogo fica vazio — o cache é reconstruído sozinho na primeira visita.
2. **Perfil do site**: nome, logo, WhatsApp/Telegram, e o token do Mercado Pago
   (se não passou por variável de ambiente).
3. **Planos**: os 3 planos padrão já vêm no seed (Quinzenal R$ 15,00 / Mensal
   R$ 25,00 / Anual R$ 70,00) — edite preço, dias e telas no admin.
4. **SMTP** (opcional): configure para recuperação de senha por e-mail.

## Teste de fumaça
- Abrir a landing pública → planos aparecem.
- Cadastrar um usuário, comprar o plano Mensal com o Mercado Pago (sandbox
  primeiro) → o premium ativa e o player abre.
- Assistir um filme e um canal ao vivo: o vídeo deve tocar em HTTPS
  (a URL do player é `/stream/proxy/...`, sem credenciais do painel).

## Notas
- O proxy dos streams pode ser desligado em `config/config.php`
  (`STREAM_PROXY_ATIVO` = false) — só faça isso se o painel tiver HTTPS próprio.
- O banco persiste no volume `streamflix_mysql`; o seed só é importado quando
  o banco está vazio. Re-deploy não apaga os dados.
- `cache/` é reconstruído a partir do painel; apagar `cache/stream.php` é seguro.
