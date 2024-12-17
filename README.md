# Backup Automático em PHP para Google Drive

Este projeto é uma solução automática para realizar backups de arquivos em PHP, enviando-os para o Google Drive. Ele permite compactar arquivos locais, fazer upload em lote e armazená-los de forma organizada em uma pasta específica do Google Drive.

## Funcionalidades

- Autenticação no Google Drive usando credenciais.
- Cria uma pasta específica para os backups no Google Drive.
- Compacta arquivos antes de realizar o upload.
- Realiza upload em lote para o Google Drive.
- Atualiza arquivos existentes no Drive, caso necessário.
- Gera logs automáticos de erros.

## Requisitos

1. **PHP 7.4 ou superior** com as extensões:
   - `zip`
   - `json`
   - `curl`
2. **Composer** para gerenciamento de dependências.
3. Credenciais da API do Google configuradas.
4. Conexão com a internet.

## Instalação

1. **Clone o repositório:**

   ```bash
   git clone https://github.com/fabioaraujob2b/backup_automatico.git
   cd backup_automatico
   ```

2. **Instale as dependências via Composer:**

   ```bash
   composer install
   ```

3. **Configure as credenciais da API do Google:**

   - Crie um projeto na [Google Cloud Console](https://console.cloud.google.com/).
   - Habilite a API do Google Drive.
   - Baixe o arquivo de credenciais `credentials.json` e coloque-o no diretório raiz do projeto.
   - Renomeie o arquivo para `access_data.json`.

4. **Configure o token de acesso:**

   - Gere o arquivo `access_token.json` com o token de acesso e insira o refresh token no formato JSON. Este arquivo será atualizado automaticamente quando necessário.

5. **Prepare o diretório de arquivos:**

   - Crie a pasta `arquivos` no diretório raiz do projeto.
   - Coloque os arquivos que deseja fazer backup dentro dela.

## Uso

Execute o script principal:

```bash
php index.php
```

- O sistema irá autenticar, criar ou atualizar a pasta no Google Drive e realizar o upload dos arquivos compactados.
- Arquivos duplicados serão atualizados no Drive.
- Após o envio, os arquivos ZIP temporários serão excluídos localmente.

## Logs de Erros

- Os erros são registrados no arquivo `error_log` no diretório raiz do projeto.
- Caso o arquivo de log não exista, ele será criado automaticamente na primeira ocorrência de erro.

## Configurações Avançadas

- **Tempo de execução:** O tempo limite está configurado para 3000 segundos. Pode ser ajustado em:
  ```php
  ini_set('max_execution_time', 3000);
  ```
- **Limite de memória:** Configurado para 5 GB. Pode ser ajustado em:
  ```php
  ini_set('memory_limit', '5G');
  ```

## Estrutura do Projeto

```
backup_automatico/
├── arquivos/                # Diretório contendo os arquivos a serem enviados
├── vendor/                  # Dependências do Composer
├── access_data.json         # Credenciais da API do Google
├── access_token.json        # Token de acesso ao Google Drive
├── error_log                # Arquivo de log de erros
├── composer.json            # Configuração das dependências
├── index.php                # Script principal
└── README.md                # Documentação do projeto
```

##
