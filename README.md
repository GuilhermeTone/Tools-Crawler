# CoreProject

Sistema Laravel para cotacao de ferramentas a partir de planilhas XLSX e buscas pontuais. O foco principal e automatizar a leitura de itens de uma planilha, buscar opcoes em lojas online, ranquear candidatos, permitir escolha manual e gerar uma planilha final preenchida com marca e valor.

## Visao geral

O sistema tem quatro frentes principais:

- **Planilhas**: upload de uma cotacao XLSX, processamento linha a linha, busca em varias lojas, selecao do melhor resultado e download da planilha preenchida.
- **Buscas especificas**: busca avulsa por ferramenta usando Google Shopping via Serper.
- **Normalizacao de busca com IA**: OpenAI e usada somente para corrigir/normalizar o termo pesquisado antes da busca. A IA nao escolhe produto e nao interpreta resultados.
- **Ranking com Meilisearch**: os produtos capturados pelos crawlers sao indexados temporariamente no Meilisearch para ordenar candidatos por relevancia, respeitando regras de medida e marca.

## Stack

- Laravel 12 / PHP 8.3
- MySQL 8
- Redis
- Laravel Horizon para filas
- Meilisearch para ranking de candidatos
- OpenAI Responses API para normalizacao de busca
- Serper Shopping API para buscas especificas
- Alpine.js + Axios + Tailwind nas telas
- Docker Compose para ambiente local

## Como rodar localmente

Subir containers:

```bash
docker compose up -d
```

Instalar dependencias PHP:

```bash
docker compose exec app composer install
```

Instalar/buildar assets:

```bash
npm install
npm run build
```

Rodar migrations:

```bash
docker compose exec app php artisan migrate
```

Limpar cache de configuracao depois de alterar `.env`:

```bash
docker compose exec app php artisan config:clear
```

A aplicacao fica em:

```text
http://localhost:8080
```

Servicos expostos:

- App/nginx: `localhost:8080`
- MySQL: `localhost:3306`
- Redis: `localhost:6379`
- Meilisearch: `localhost:7700`

## Variaveis importantes

As configuracoes externas ficam em [config/services.php](config/services.php).

OpenAI:

```env
OPENAI_ENABLED=true
OPENAI_NORMALIZE_SEARCH_ENABLED=true
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.4-nano
OPENAI_FALLBACK_MODEL=gpt-4.1-nano
```

Meilisearch:

```env
MEILISEARCH_ENABLED=true
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=
MEILISEARCH_MATCHING_STRATEGY=last
MEILISEARCH_MAX_RESULTS=30
MEILISEARCH_MIN_RANKING_SCORE=0
```

Serper:

```env
SERPER_API_KEY=
```

Assinatura:

O sistema usa Laravel Cashier. As rotas principais exigem usuario autenticado e assinatura ativa pelo middleware [EnsureSubscribed.php](app/Http/Middleware/EnsureSubscribed.php).

## Fluxo da planilha

1. Usuario envia uma planilha XLSX em `/planilhas`.
2. [PlanilhaCotacaoController.php](app/Http/Controllers/PlanilhaCotacaoController.php) salva o arquivo em `storage/app/planilhas/originais`.
3. [XlsxCotacaoService.php](app/Services/Planilhas/XlsxCotacaoService.php) le a primeira aba do XLSX.
4. Cada linha valida vira um [PlanilhaCotacaoItem](app/Models/PlanilhaCotacaoItem.php).
5. [ProcessarPlanilhaCotacaoJob.php](app/Jobs/ProcessarPlanilhaCotacaoJob.php) cria um batch de jobs, um por item e loja.
6. [ProcessarPlanilhaCotacaoItemLojaJob.php](app/Jobs/ProcessarPlanilhaCotacaoItemLojaJob.php) busca o item em uma loja especifica.
7. Cada loja usa um scraper em [app/Services/Scrapers](app/Services/Scrapers).
8. [CrawlerService.php](app/Services/CrawlerService.php) normaliza o termo, executa o scraper, remove duplicados, enriquece produtos e chama o ranking.
9. [MeilisearchProductSearchService.php](app/Services/MeilisearchProductSearchService.php) ranqueia os candidatos e aplica regras de qualidade.
10. Os resultados sao salvos no campo `resultados` do item.
11. [FinalizarPlanilhaCotacaoJob.php](app/Jobs/FinalizarPlanilhaCotacaoJob.php) fecha o processamento e gera a planilha processada.
12. Na tela, o usuario escolhe o resultado, ajusta margem e baixa a planilha final.

## Estrutura da planilha

O leitor XLSX esta em [XlsxCotacaoService.php](app/Services/Planilhas/XlsxCotacaoService.php).

Colunas principais usadas:

- `C`: descricao do produto pesquisado.
- `J`: marca cotada preenchida na planilha final.
- `N`: valor unitario preenchido na planilha final.
- `A`, `B`, `D`, `F`, `L`, `M`, `O`: campos auxiliares preservados/lidos para contexto.

Linhas antes da linha 4 sao ignoradas.

## Busca e normalizacao com IA

A IA fica em [EcommerceAiNormalizerService.php](app/Services/EcommerceAiNormalizerService.php).

Ela faz apenas isto:

- recebe o termo original;
- corrige erros comuns, por exemplo `griffo` para `grifo`;
- preserva marca, modelo, codigo, voltagem e medidas;
- devolve uma lista curta de termos de busca.

Ela nao faz isto:

- nao escolhe o produto vencedor;
- nao parseia HTML de loja;
- nao avalia se um resultado e correto;
- nao altera preco, marca ou resultado final.

Se a OpenAI estiver desligada, sem chave, falhar ou retornar baixa confianca, o sistema usa fallback local e pesquisa o termo original.

## Crawlers de lojas

Os crawlers ficam em [app/Services/Scrapers](app/Services/Scrapers).

Cada scraper implementa [ScraperInterface.php](app/Services/Scrapers/ScraperInterface.php) e normalmente herda utilitarios de [BaseScraper.php](app/Services/Scrapers/BaseScraper.php).

O [CrawlerService.php](app/Services/CrawlerService.php) registra as lojas ativas:

- Loja do Mecanico
- Anhanguera
- Ant Ferramentas
- Kennedy
- LF Maquinas
- Martineli
- Mabore
- Fer Maquinas
- Casa do Frentista
- Agreli Maquinas
- Palacio das Ferramentas
- Brenfeer
- Tramontina Oficial
- Dimensional
- Gravia
- Arcazul
- Minas Ferramentas

Para adicionar uma loja:

1. criar um scraper em `app/Services/Scrapers`;
2. implementar `identificador()`, `nomeSite()` e `buscar()`;
3. registrar a instancia no array `$scrapers` de [CrawlerService.php](app/Services/CrawlerService.php);
4. adicionar testes quando possivel.

## Enriquecimento de produto

[ProductEnrichmentService.php](app/Services/ProductEnrichmentService.php) extrai e normaliza informacoes dos produtos:

- marca detectada;
- categoria;
- tipo de ferramenta;
- modelo/codigo;
- potencia;
- voltagem;
- medida;
- peso;
- material;
- score legado de produto em alguns fluxos.

Ele tambem concentra a lista de marcas conhecidas e regras como:

- busca com multiplas marcas aceita qualquer marca da lista solicitada;
- marca diferente pode zerar correspondencia;
- tipo incompativel pode zerar resultado;
- medidas explicitas precisam bater.

## Ranking com Meilisearch

[MeilisearchProductSearchService.php](app/Services/MeilisearchProductSearchService.php) usa Meilisearch como motor de ranking, nao como unica fonte de verdade.

Fluxo:

1. recebe os produtos capturados pelos crawlers;
2. remove medidas incompativeis, por exemplo busca `1/4` nao aceita produto `1/8`;
3. respeita marcas solicitadas, por exemplo `GEDORE/BELZER/ROBUST` nao aceita `NOVE54`;
4. cria um indice temporario;
5. indexa os candidatos;
6. busca pelo termo normalizado;
7. marca hits com `match_meilisearch=true` e `score_meilisearch`;
8. mantem candidatos validos restantes como fallback, ate o limite configurado;
9. remove o indice temporario.

Configuracao atual importante:

- `matchingStrategy=last`: deixa a busca menos agressiva que `all`.
- `max_results=30`: limite de candidatos retornados pelo ranking.
- `min_ranking_score=0`: sem corte minimo por score do Meilisearch.

## Regras de marca e medida

Estas regras existem para evitar falsos positivos baratos.

Medida:

- Se a busca tem medida explicita, o produto com outra medida explicita e removido.
- Exemplo: `CHAVE PHILIPS 1/4 X 4` remove `Chave Philips 1/8 X 4`.

Marca:

- Se a busca pede marcas conhecidas, produtos com marca conhecida diferente sao removidos.
- Exemplo: `GEDORE/BELZER/ROBUST` remove `NOVE54`.
- Se houver pelo menos um resultado com marca solicitada, resultados sem marca tambem sao descartados.
- Se nao houver nenhum resultado com marca solicitada, resultado sem marca pode ficar como fallback.

## Tela de planilha

Backend:

- [PlanilhaCotacaoController.php](app/Http/Controllers/PlanilhaCotacaoController.php)
- [PlanilhaCotacao.php](app/Models/PlanilhaCotacao.php)
- [PlanilhaCotacaoItem.php](app/Models/PlanilhaCotacaoItem.php)

Frontend:

- [resources/views/planilhas/index.blade.php](resources/views/planilhas/index.blade.php)
- [resources/views/planilhas/show.blade.php](resources/views/planilhas/show.blade.php)
- [public/js/planilhas/show-controller.js](public/js/planilhas/show-controller.js)
- [public/js/planilhas/tags.js](public/js/planilhas/tags.js)
- [public/js/planilhas/formatters.js](public/js/planilhas/formatters.js)
- [public/js/planilhas/constants.js](public/js/planilhas/constants.js)

A tela:

- faz polling em `/planilhas/{id}/status`;
- mostra progresso por item e por loja;
- permite abrir/fechar resultados por linha;
- mostra tags como menor preco, alta confianca, marca correta, medida correta e conferir;
- permite escolher resultado;
- aplica margem percentual;
- refaz busca de uma linha especifica;
- revalida precos selecionados;
- atualiza o link de download.

## Revalidacao

[PlanilhaRevalidacaoService.php](app/Services/Planilhas/PlanilhaRevalidacaoService.php) tenta reencontrar o produto selecionado na loja original.

Ele compara:

- URL normalizada;
- similaridade do nome;
- score do produto/ranking;
- preco atual.

Status possiveis:

- `pendente`
- `ok`
- `preco_alterado`
- `nao_encontrado`
- `erro`

Ao baixar a planilha, o controller revalida os selecionados antes de gerar o arquivo final.

## Buscas especificas

A tela `/buscas-especificas` usa [FerramentaController.php](app/Http/Controllers/FerramentaController.php).

Fluxo:

1. usuario informa um termo;
2. cria um [FerramentaBusca](app/Models/FerramentaBusca.php);
3. dispara [BuscarSerperShoppingJob.php](app/Jobs/BuscarSerperShoppingJob.php);
4. [SerperShoppingService.php](app/Services/SerperShoppingService.php) consulta Google Shopping via Serper;
5. [SerperRelevanceService.php](app/Services/SerperRelevanceService.php) aplica score/relevancia;
6. resultados sao salvos em [ResultadoBusca](app/Models/ResultadoBusca.php);
7. tela consulta status e exibe resultados.

Esse fluxo e separado da busca de planilhas por crawlers.

## Filas e Horizon

Jobs principais:

- [ProcessarPlanilhaCotacaoJob.php](app/Jobs/ProcessarPlanilhaCotacaoJob.php): prepara batch da planilha.
- [ProcessarPlanilhaCotacaoItemLojaJob.php](app/Jobs/ProcessarPlanilhaCotacaoItemLojaJob.php): processa um item em uma loja.
- [FinalizarPlanilhaCotacaoJob.php](app/Jobs/FinalizarPlanilhaCotacaoJob.php): gera arquivo final e marca planilha como concluida.
- [BuscarSerperShoppingJob.php](app/Jobs/BuscarSerperShoppingJob.php): executa busca especifica no Serper.

No Docker, o container `horizon` roda:

```bash
php artisan horizon
```

Reiniciar workers depois de mudancas em jobs/services:

```bash
docker compose restart horizon
```

## Banco de dados

Modelos principais:

- [User.php](app/Models/User.php): usuario e assinatura.
- [PlanilhaCotacao.php](app/Models/PlanilhaCotacao.php): arquivo enviado e estado geral.
- [PlanilhaCotacaoItem.php](app/Models/PlanilhaCotacaoItem.php): linha da planilha e resultados encontrados.
- [FerramentaBusca.php](app/Models/FerramentaBusca.php): busca especifica avulsa.
- [ResultadoBusca.php](app/Models/ResultadoBusca.php): resultado da busca especifica.
- [Orcamento.php](app/Models/Orcamento.php) e [OrcamentoItem.php](app/Models/OrcamentoItem.php): legado/redirecionado para planilhas.

Migrations ficam em [database/migrations](database/migrations).

## Arquivos gerados

Planilhas originais:

```text
storage/app/planilhas/originais
```

Planilhas processadas:

```text
storage/app/planilhas/processadas
```

## Testes

Rodar todos os testes:

```bash
php artisan test
```

Rodar apenas unitarios:

```bash
php artisan test --testsuite=Unit
```

Rodar dentro do Docker:

```bash
docker compose exec app php artisan test --testsuite=Unit
```

Testes importantes:

- [EcommerceAiNormalizerServiceTest.php](tests/Unit/EcommerceAiNormalizerServiceTest.php)
- [MeilisearchProductSearchServiceTest.php](tests/Unit/MeilisearchProductSearchServiceTest.php)
- [ProductEnrichmentServiceTest.php](tests/Unit/ProductEnrichmentServiceTest.php)
- [XlsxCotacaoServiceTest.php](tests/Unit/Planilhas/XlsxCotacaoServiceTest.php)
- [PlanilhaRevalidacaoServiceTest.php](tests/Unit/Planilhas/PlanilhaRevalidacaoServiceTest.php)
- [QueryNormalizerTest.php](tests/Unit/Scrapers/QueryNormalizerTest.php)
- [DisponibilidadeScraperTest.php](tests/Unit/Scrapers/DisponibilidadeScraperTest.php)

## Problemas comuns

### `Class "ZipArchive" not found`

O PHP precisa da extensao `zip`. O Dockerfile ja instala `libzip-dev`, `zip` e `docker-php-ext-install zip`.

Rebuild:

```bash
docker compose build app horizon
docker compose up -d app horizon
```

### `502 Bad Gateway`

Normalmente e nginx sem conseguir falar com o PHP-FPM do container `app`.

Checar containers:

```bash
docker compose ps
```

Ver logs:

```bash
docker compose logs --tail=80 nginx
docker compose logs --tail=80 app
```

Reiniciar nginx:

```bash
docker compose restart nginx
```

### Mudou `.env` e nada mudou

Limpar config:

```bash
docker compose exec app php artisan config:clear
```

Reiniciar workers:

```bash
docker compose restart horizon
```

### Resultado errado ja apareceu na tela

Resultados ficam gravados no item da planilha. Depois de corrigir regra de busca, clique em **Editar** / **refazer busca** na linha para substituir os resultados antigos.

## Onde mexer para cada tipo de mudanca

- Normalizacao de busca por IA: [EcommerceAiNormalizerService.php](app/Services/EcommerceAiNormalizerService.php)
- Ranking e filtros de candidatos: [MeilisearchProductSearchService.php](app/Services/MeilisearchProductSearchService.php)
- Marcas, medidas, tipos e atributos: [ProductEnrichmentService.php](app/Services/ProductEnrichmentService.php)
- Cadastro de lojas/crawlers ativos: [CrawlerService.php](app/Services/CrawlerService.php)
- Scraping de uma loja especifica: [app/Services/Scrapers](app/Services/Scrapers)
- Leitura/escrita XLSX: [XlsxCotacaoService.php](app/Services/Planilhas/XlsxCotacaoService.php)
- Fluxo web de planilhas: [PlanilhaCotacaoController.php](app/Http/Controllers/PlanilhaCotacaoController.php)
- UI de resultados da planilha: [public/js/planilhas](public/js/planilhas)
- Jobs/fila: [app/Jobs](app/Jobs)
- Assinatura/acesso: [AssinaturaController.php](app/Http/Controllers/AssinaturaController.php) e [EnsureSubscribed.php](app/Http/Middleware/EnsureSubscribed.php)
