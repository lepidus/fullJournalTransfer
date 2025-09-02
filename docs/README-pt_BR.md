[English](/README.md) | **Português Brasileiro**

# Transferência Completa de Periódico
Este plugin permite importar e exportar todo o conteúdo de um periódico.

## Compatibilidade
A versão mais recente deste plugin é compatível com as seguintes aplicações do PKP:

* OJS 3.3.0

**Nota:** Este plugin é projetado para a exportação e importação de periódicos dentro da mesma versão do OJS. Exemplo: de `3.3.0-16` para `3.3.0-16`. Para melhores resultados, recomenda-se utilizar a versão OJS 3.3.0-16 ou mais recente.

## Requisitos

- PHP >= 7.4
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

⚠️ Durante o processo de importação, um email de "Registro de Periódico" é enviado para todos os usuários importados. Para fins de teste, recomendamos fortemente desativar a funcionalidade de email no arquivo config.inc.php antes de rodar o script de importação.

**Obs**.: Periódicos contendo uma quantidade substancial de dados irão consumir muitos recursos de memória. Nesses casos, utilize o parâmetro PHP `-d memory_limit=-1` durante as operações de importação/exportação.

## Solução de problemas

Este plugin utiliza recursos dos plugins de importação/exportação nativo e de usuários. Se a execução não funcionar como esperado, teste os plugins de importação/exportação do PKP para resolver quaisquer problemas antes de continuar com este.

## Efeitos Colaterais

Alguns comportamentos são esperados ao executar a importação da revista:

- Todos os IDs no banco de dados serão modificados, invalidando referências externas.
- Os logins dos usuários serão alterados.
- Alguns registros de métricas podem ser perdidos.

## Conteúdo Importado/Exportado do Periódico

**Usando a importação/exportação nativa da PKP**:

- Usuários e Papéis de Usuário
- Artigos
- Edições

**Adicionado**:

- Dados do Periódico
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
lib/pkp/lib/vendor/phpunit/phpunit/phpunit -c lib/pkp/tests/phpunit-env2.xml plugins/importexport/fullJournalTransfer/tests
```

# Créditos
Este plugin foi idealizado e patrocinado pelo Instituto Brasileiro de Informação em Ciência e Tecnologia (IBICT) para a versão 2.x do OJS.

O financiamento para a versão 3.3 vem da Universidade Federal de São Paulo (Unifesp) e da Universidade Federal do Recôncavo da Bahia (UFRB).

Desenvolvido pela Lepidus Tecnologia.

# Licença
Este plugin é licenciado sob a Licença Pública Geral GNU v3.0

Copyright (c) 2014-2024 Lepidus Tecnologia