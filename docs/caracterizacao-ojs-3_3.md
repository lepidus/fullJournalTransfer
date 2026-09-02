# Caracterização dos contratos legados

Este documento registra os comportamentos observáveis do Full Journal Transfer no OJS 3.3 que
orientam a adaptação para OJS 3.4. Ele descreve o ponto de partida, não o formato final da
transferência.

## Classificação dos comportamentos

### Preservados

- A transferência ocorre entre instalações na mesma release de manutenção do OJS.
- O pacote contém um XML na raiz e os arquivos do periódico sob `journals/<id>/`.
- Os caminhos armazenados no pacote são relativos; caminhos absolutos da instalação não são
  expostos.
- O conteúdo inclui dados do periódico, usuários e papéis, edições, submissões, publicações,
  arquivos, formulários e workflow editorial, discussões e métricas.
- IDs da origem identificam relações dentro do pacote e são remapeados para os IDs efetivos no
  destino.
- A importação deve preservar relações e ordenações sem depender dos mesmos IDs no banco de
  destino.

### Redesenhados

- O XML monolítico será substituído por um pacote com manifesto e versão explícitos, mantendo XML
  como representação dos dados e reutilizando os contratos Native XML do OJS 3.4.
- A criação direta de entidades por DAOs será substituída por repositórios, collectors e serviços
  do OJS 3.4, com um único mapa de IDs por importação.
- A manipulação de arquivos com comandos de shell e rollback parcial será substituída por staging,
  validação de caminhos e compensação coordenada entre banco e filesystem.
- Configurações de plugins serão restauradas somente para plugins e propriedades permitidos, em vez
  de confiar em nomes arbitrários recebidos no XML.
- Conflitos de usuários serão tratados por uma política explícita e auditável, sem alterar login de
  forma implícita.
- Métricas serão gravadas por caminhos próprios de restauração, preservando data e dimensões sem
  disparar novamente o workflow normal.

### Descartados

- Parsing manual dos argumentos do CLI que já é oferecido pelo Native XML do OJS 3.4.
- Classes globais, arquivos `.inc.php`, `import()`, DAOs removidos e estado global compartilhado.
- Dependência de caminhos absolutos ou IDs fixos da instalação de origem.
- Perda silenciosa de métricas, datas, autoria ou relações que o pacote declara suportar.
- Efeitos colaterais do workflow durante a restauração, como novos e-mails e notificações.
- Histórico de eventos editoriais e e-mails enviados (`event_log`, `event_log_settings`, `email_log` e
  `email_log_users`).
- Aceitação irrestrita de entradas, propriedades ou arquivos indicados pelo pacote.

## Fixtures

As fixtures em `tests/samples` usam IDs locais somente para expressar relações internas. Os testes
não consultam objetos preexistentes por esses IDs. Os arquivos de artigo e edição permitem
caracterizar a estrutura do pacote sem depender do diretório de arquivos de uma instalação real.

`article-contracts.xml` representa uma submissão fora de edição com duas versões de publicação, duas rodadas
de avaliação, decisões e uma discussão com anexo. `journal.xml` representa uma edição não publicada
e uma métrica com dimensões geográficas e de tipo de arquivo. O teste de contrato lê essas fixtures
como XML e protege somente essas propriedades observáveis; não referencia filtros, DAOs ou métodos
internos do plugin legado.

O teste de archive protege a estrutura mínima aceita do pacote legado: `journal.xml` na raiz,
arquivos sob `journals/<id>/` e ausência de caminhos absolutos. Essa estrutura é evidência para o
manifesto e o archive seguro das histórias seguintes, não uma obrigação de preservar a ferramenta
de compactação antiga.
