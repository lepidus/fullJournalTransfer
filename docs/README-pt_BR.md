[English](/README.md) | **Português Brasileiro**

# Transferência Completa de Periódico
Este plugin permite importar e exportar todo o conteúdo de um periódico.

## Compatibilidade
A versão mais recente deste plugin é compatível com as seguintes aplicações do PKP:

* OJS 3.4.0

**Nota:** Os pacotes só podem ser transferidos entre instalações da mesma linha OJS 3.4.0.

## Requisitos

- PHP >= 8.0.2
- php-mbstring
- php-intl
- php-xml

## Download do Plugin
Para baixar o plugin, vá para a [página de Releases](https://github.com/lepidus/fullJournalTransfer/releases) e baixe o pacote tar.gz da versão mais recente compatível com o seu site.

## Instalação
1. Entre na área administrativa do seu site OJS através do __Painel de Controle__.
2. Navegue até `Configurações`> `Website`> `Plugins`> `Carregar um novo plugin`.
3. Em __Carregar arquivo__, selecione o arquivo __fullJournalTransfer.tar.gz__.
4. Clique em __Salvar__ e o plugin será instalado no seu site.

## Instruções de uso

### Linha de comando

#### Exportação
Exporte um periódico para um arquivo tar.gz contendo o diretório xml e de arquivos executando o comando no diretório raiz da aplicação:
```bash
php tools/importExport.php FullJournalImportExportPlugin export [nomeDoArquivoTarGz] [caminho_do_periodico]
```

#### Importação
Para importar um periódico a partir de um arquivo tar.gz, execute o comando no diretório raiz da aplicação:
```bash
php tools/importExport.php FullJournalImportExportPlugin import [nomeDoArquivoTarGz] [nome_do_usuario]
```

**Obs**.: Periódicos contendo uma quantidade substancial de dados irão consumir muitos recursos de memória. Nesses casos, utilize o parâmetro PHP `-d memory_limit=-1` durante as operações de importação/exportação.

## Solução de problemas

Este plugin utiliza recursos dos plugins de importação/exportação nativo e de usuários. Se a execução não funcionar como esperado, teste os plugins de importação/exportação do PKP para resolver quaisquer problemas antes de continuar com este.

## Efeitos Colaterais

Alguns comportamentos são esperados ao executar a importação da revista:

- Todos os IDs no banco de dados serão modificados, invalidando referências externas.
- O periódico importado é criado inicialmente desabilitado.
- Uma segunda importação do mesmo caminho de periódico é rejeitada sem duplicar conteúdo.
- Os pacotes exportados contêm conflitos de interesse dos autores e devem ser tratados como dados sensíveis.
- As datas históricas de modificação das submissões são preservadas. Após a importação, reconstrua o índice de busca e
  limpe os caches da aplicação conforme o procedimento operacional da instalação de destino. Consumidores OAI devem
  fazer uma coleta completa após mudar para a revista de destino, em vez de depender apenas de uma janela incremental.
- O tema selecionado e suas opções declaradas são transferidos quando o código do plugin de tema está instalado no OJS
  de destino. Quando ele não está disponível, o periódico importado usa o tema padrão com suas opções padrão.
- Métricas institucionais exigem um identificador ROR válido; registros sem ROR estável são rejeitados.

## Conteúdo Importado/Exportado do Periódico

**Usando a importação/exportação nativa da PKP**:

- Usuários e Papéis de Usuário
- Artigos
- Edições

**Adicionado**:

- Dados do Periódico
- Nomes públicos preferenciais e conflitos de interesse dos autores
- Datas históricas das submissões e horários de publicação das edições
- Progresso das submissões incompletas no assistente
- Tema selecionado e opções do tema
- Menus de Navegação
- Configurações de Plugins
- Seções
- Formulários de Avaliação
- Designações de Avaliação
- Rodadas de Avaliação
- Arquivos de Avaliação
- Arquivos do Avaliador
- Comentários do Avaliador
- Arquivos de Avaliação
- Designações de Estágios
- Decisões do Editor
- Discussões
- Métricas

## Execução de Testes

### Testes de Unidade
Para executar os testes unitários, rode o seguinte comando no diretório raiz da Aplicação PKP:
```bash
lib/pkp/lib/vendor/bin/phpunit -c lib/pkp/tests/phpunit.xml --no-coverage plugins/importexport/fullJournalTransfer/tests
```

### Round trip

```bash
php plugins/importexport/fullJournalTransfer/tests/round-trip/run \
  --fixture plugins/importexport/fullJournalTransfer/tests/round-trip/fixture-ojs-3.4.0.10-v1.tar.gz \
  --expected plugins/importexport/fullJournalTransfer/tests/round-trip/expected-ojs-3.4.0.10-v1.json \
  --app-root [raiz_do_ojs] \
  --files-dir [diretorio_de_arquivos] \
  --public-dir [diretorio_publico] \
  --database [nome_da_base] \
  --mysql-command [comando_mysql] \
  --inventory-command plugins/importexport/fullJournalTransfer/tests/round-trip/inventory \
  --apply
```

# Créditos
Este plugin foi idealizado e patrocinado pelo Instituto Brasileiro de Informação em Ciência e Tecnologia (IBICT) para a versão 2.x do OJS.

O financiamento para a versão 3.3 vem da Universidade Federal de São Paulo (Unifesp) e da Universidade Federal do Recôncavo da Bahia (UFRB).

Desenvolvido pela Lepidus Tecnologia.

# Licença
Este plugin é licenciado sob a Licença Pública Geral GNU v3.0

Copyright (c) 2014-2026 Lepidus Tecnologia
