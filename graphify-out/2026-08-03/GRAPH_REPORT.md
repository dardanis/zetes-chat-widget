# Graph Report - zetes-chat-widget  (2026-07-30)

## Corpus Check
- 208 files · ~88,227 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3667 nodes · 9870 edges · 165 communities (139 shown, 26 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 634 edges (avg confidence: 0.69)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `16e9edb3`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Rgdfdfoa Addasyncvalidators
- Addasyncvalidators Addvalidators
- Registeronchange Subscribe
- Canceldelete Canceledit
- Activatedcomponentref Activatedroute
- Addclass Applyvaluetoinputsignal
- Php Destroy
- Php Test
- Test User
- Attachtoappref Createcomponentref
- Get Appendchild
- Php Construct
- Attach Createcomment
- Applystyles Applytohost
- Atlassianconnection Paginationmeta
- Warn Addclass
- Addcontrol Setvalue
- Component Page
- Closepanel Feedbackfor
- Destroy Adjustindex
- Constructor Addheaderentry
- Component Createsession
- Unsubscribe Add
- Remove Unsubscribe
- Ragapiservice Changeownpassword
- Complete Documentstatusentries
- Applypaginationmeta Confirmdeletedocument
- Chatdisplaytimestamp Createtext
- Appendall Areallvisibleconfluencespacesselected
- Constructor Assignasyncvalidators
- Bootstrap Afterpreactivation
- Test Document
- Getuseragent Producermustrecompute
- Attachtoviewcontainerref Chatdisplaysubtitle
- Fac Withconfig
- Core Group 35
- Allcontrolsdisabled Applyformstate
- Test Confluence
- Widgetchatcontroller Projectcontroller
- Changeownpassword Clearstoreduser
- Component Theme
- Test User
- Attach Clone
- Component Page
- Component Chatmessage
- Attachview Compilemoduleandallcomponentsasync
- Construct Php
- Angular Cli
- Component Overview
- Capture Consumeoptional
- Angular Common
- Vite Tailwindcss
- Element Domain
- Construct Contextretrievalservice
- Server Routes
- Activate Activatechildroutes
- Chateventhandlers Reverbservice
- Createsnapshot Expandsegmentagainstrouteusingredirect
- Detectcontenttypeheader Addeventlistener
- Angular Shell
- Php Authorizetelescopeaccess
- Appendchild Applystyles
- Path Getpath
- Crawledurlstotalpages Gotocrawledurlspage
- Createurltree Hasactivelinks
- Php Artisan
- Composer Json
- Detectchanges Cleanup
- Confluencedocumenterror Hasconfluencesyncissue
- Widget Test
- Styles Browser
- Manageduser Component
- Checkforerrors Checkname
- Laravel Ext
- Committransition Createbrowserpath
- Test Parser
- Php Documentparseexception
- Illuminate Projectchatmessagecreated
- Parse Supports
- Component Widgetsettings
- Afterrun Bootstrap
- Construct Pdfparser
- Build Builder
- Build Scripts
- Tsconfig Widget
- Registeronvalidatorchange Domain
- Docxparser Phpoffice
- Xmlparser Php
- Install Php
- Emit Handleerror
- Pestphp Pest
- Laravel Mockery
- Angular Json
- Builder Serve
- Csvparser Php
- Jsonparser Flatten
- Userfactory Php
- Configurations Development
- Prefix Projecttype
- Appserviceprovider Php
- Textparser Normalizetext
- Database Autoload
- Databaseseeder Illuminate
- Production Budgets
- Package Json
- Server Angularapp
- Ngvalue Setelementvalue
- Angular Cli
- Create Telescope
- Exampletest True
- Validatecsrftoken Php
- Keywords Framework
- Php Artisan
- Angular Platform
- Express Domain
- Pusher Domain
- Vitest Domain
- Cache Php
- Bootstrap Loading
- .execute
- .register
- @angular/core

## God Nodes (most connected - your core abstractions)
1. `e` - 300 edges
2. `t()` - 197 edges
3. `e` - 158 edges
4. `u()` - 138 edges
5. `Project` - 79 edges
6. `User` - 78 edges
7. `N()` - 69 edges
8. `constructor()` - 63 edges
9. `RagApiService` - 58 edges
10. `ProjectDocumentsPageComponent` - 56 edges

## Surprising Connections (you probably didn't know these)
- `Confluence Ingestion Flow` --conceptually_related_to--> `RAG MVP Architecture`  [INFERRED]
  docs/confluence-ingestion.md → README-RAG-MVP.md
- `Laravel Framework Baseline` --conceptually_related_to--> `RAG MVP Architecture`  [INFERRED]
  README.md → README-RAG-MVP.md
- `VoiceResponseFormatterTest` --references--> `VoiceResponseFormatter`  [EXTRACTED]
  tests/Unit/VoiceResponseFormatterTest.php → app/Services/Voice/VoiceResponseFormatter.php
- `Fr()` --indirect_call--> `t()`  [INFERRED]
  public/widget/main.js → public/ng/main-3PFVQUYL.js
- `Self Hosted Deploy Job` --conceptually_related_to--> `Angular NG Deployment Shell`  [INFERRED]
  .github/workflows/ci-self-hosted.yml → public/ng/index.html

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Widget Runtime Delivery Flow** — readme_rag_mvp_widget_security_controls, public_chat_zetes_chat_embed_configuration, public_widget_index_angular_widget_deployment_shell [INFERRED 0.85]
- **Angular Multi-Target Build Outputs** — frontend_readme_angular_cli_workflow, public_ng_index_angular_ng_deployment_shell, public_widget_index_angular_widget_deployment_shell, public_ng_3rdpartylicenses_third_party_licenses_manifest, public_widget_3rdpartylicenses_third_party_licenses_manifest [INFERRED 0.75]
- **RAG Ingestion and Sync Boundary** — readme_rag_mvp_rag_mvp_architecture, readme_rag_mvp_chunking_strategy_sliding_window, docs_confluence_ingestion_confluence_ingestion_flow, docs_confluence_ingestion_rag_queue_sync_mechanism [INFERRED 0.85]

## Communities (165 total, 26 thin omitted)

### Community 0 - "Rgdfdfoa Addasyncvalidators"
Cohesion: 0.01
Nodes (121): addMatch(), Af(), Ah(), ai(), applyRedirectCommands(), applyRedirectCreateUrlTree(), applyTheme(), assertInAngularZone() (+113 more)

### Community 1 - "Addasyncvalidators Addvalidators"
Cohesion: 0.02
Nodes (158): cE(), add(), addAsyncValidators(), addClass(), _addParent(), addValidators(), _allControlsDisabled(), _anyControls() (+150 more)

### Community 2 - "Registeronchange Subscribe"
Cohesion: 0.02
Nodes (32): iM(), mE(), vf(), afterRun(), append(), appendChild(), _assignValidators(), attachToAppRef() (+24 more)

### Community 3 - "Canceldelete Canceledit"
Cohesion: 0.05
Nodes (106): ae(), assignPhoneNumber(), be(), bg(), bootstrap(), Bt(), cg(), changeOwnPassword() (+98 more)

### Community 5 - "Addclass Applyvaluetoinputsignal"
Cohesion: 0.05
Nodes (80): a1(), applyPaginationMeta(), assign(), at(), Bv(), clearTimeout(), confirmDeleteDocument(), connectAndLoadConfluenceSpaces() (+72 more)

### Community 6 - "Php Destroy"
Cohesion: 0.06
Nodes (16): AccountController, UserController, AuthenticatedSessionController, Controller, CountryController, OllamaProxyController, ProjectChatController, ProjectController (+8 more)

### Community 7 - "Php Test"
Cohesion: 0.04
Nodes (17): ChatMessage, ChatMessageFeedback, Country, DocumentChunk, MessageCitation, ProjectConfluenceSpace, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo (+9 more)

### Community 8 - "Test User"
Cohesion: 0.05
Nodes (15): Tenant, User, AccessControlService, Illuminate\Database\Eloquent\Builder, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable (+7 more)

### Community 9 - "Attachtoappref Createcomponentref"
Cohesion: 0.05
Nodes (57): addControl(), _allControlsDisabled(), _anyControls(), _anyControlsDirty(), _anyControlsHaveStatus(), _anyControlsTouched(), _applyFormState(), asyncValidator() (+49 more)

### Community 10 - "Get Appendchild"
Cohesion: 0.07
Nodes (40): fh(), Qh(), tM(), Am(), ay(), B(), co(), cy() (+32 more)

### Community 11 - "Php Construct"
Cohesion: 0.07
Nodes (15): CrawlProjectWebsiteJob, EmbedDocumentChunkJob, ProcessProjectDocumentJob, ResyncConfluenceDocumentJob, SyncProjectConfluenceSpaceJob, ProjectDocument, DocumentChunkingService, DocumentParserRegistry (+7 more)

### Community 12 - "Attach Createcomment"
Cohesion: 0.06
Nodes (47): cb(), createSnapshot(), di(), dt(), ec(), Ed(), eo(), expandSegmentAgainstRouteUsingRedirect() (+39 more)

### Community 13 - "Applystyles Applytohost"
Cohesion: 0.05
Nodes (43): runTask(), applyStyles(), applyToHost(), _assignAsyncValidators(), be(), createComponentRef(), detachFromAppRef(), Dp() (+35 more)

### Community 14 - "Atlassianconnection Paginationmeta"
Cohesion: 0.07
Nodes (6): AtlassianConnection, PaginationMeta, ProjectConfluenceSpace, ProjectDocument, ProjectDocumentsPageComponent, Component

### Community 15 - "Warn Addclass"
Cohesion: 0.07
Nodes (36): Ar(), at(), au(), Bd(), bm(), dm(), Ea(), fm() (+28 more)

### Community 16 - "Addcontrol Setvalue"
Cohesion: 0.06
Nodes (43): _0(), addClass(), _adjustIndex(), b1(), bindRealtime(), c0(), c1(), Dc() (+35 more)

### Community 17 - "Component Page"
Cohesion: 0.08
Nodes (14): authGuard(), guestGuard(), ProjectLayoutComponent, Component, DashboardPageComponent, Component, LoginPageComponent, Component (+6 more)

### Community 18 - "Closepanel Feedbackfor"
Cohesion: 0.06
Nodes (68): 594(), A(), ai(), Ao(), bf(), bn(), _c(), cc() (+60 more)

### Community 19 - "Destroy Adjustindex"
Cohesion: 0.11
Nodes (44): cancelDelete(), canConnectConfluence(), closeCreateModal(), closeDeleteModal(), confirmDelete(), confluenceDocumentError(), cR(), ek() (+36 more)

### Community 20 - "Constructor Addheaderentry"
Cohesion: 0.04
Nodes (80): qo(), addHeaderEntry(), aE(), Ah(), appendAll(), applyUpdate(), bh(), bt() (+72 more)

### Community 21 - "Component Createsession"
Cohesion: 0.07
Nodes (12): Component, Input, WidgetTheme, ZetesChatComponent, ChatMessage, Citation, CreateSessionResponse, SendMessageResponse (+4 more)

### Community 23 - "Remove Unsubscribe"
Cohesion: 0.10
Nodes (27): aa(), ad(), ag(), bo(), bu(), cd(), dd(), dispatchEvent() (+19 more)

### Community 25 - "Complete Documentstatusentries"
Cohesion: 0.07
Nodes (41): ad(), add(), _addParent(), complete(), createText(), _d(), enqueue(), error() (+33 more)

### Community 26 - "Applypaginationmeta Confirmdeletedocument"
Cohesion: 0.09
Nodes (40): _2(), b2(), bk(), c2(), copyEmbedCode(), dk(), e2(), F2() (+32 more)

### Community 27 - "Chatdisplaytimestamp Createtext"
Cohesion: 0.06
Nodes (55): _1(), ar(), aS(), b0(), bc(), Bn(), createComponentRef(), CT() (+47 more)

### Community 28 - "Appendall Areallvisibleconfluencespacesselected"
Cohesion: 0.07
Nodes (40): a0(), bi(), detectChanges(), ea(), Ff(), fn(), getLView(), getUserAgent() (+32 more)

### Community 29 - "Constructor Assignasyncvalidators"
Cohesion: 0.07
Nodes (38): addHeaderEntry(), aM(), applyUpdate(), Bd(), bp(), ch(), constructor(), createElement() (+30 more)

### Community 30 - "Bootstrap Afterpreactivation"
Cohesion: 0.09
Nodes (27): ab(), attach(), attachToViewContainerRef(), bs(), ci(), create(), createComponent(), createEmbeddedView() (+19 more)

### Community 31 - "Test Document"
Cohesion: 0.07
Nodes (28): 10. Phase 8 — Tests, 11. Sequencing and effort, 12. Risks, 13. Open items for later, 1. Architecture, 2. Phase 0 — Prerequisites, 3. Phase 1 — Data model, 4. Phase 2 — Twilio integration service (+20 more)

### Community 32 - "Getuseragent Producermustrecompute"
Cohesion: 0.13
Nodes (23): Ba(), createComment(), dy(), ef(), Fi(), fy(), Gd(), gn() (+15 more)

### Community 33 - "Attachtoviewcontainerref Chatdisplaysubtitle"
Cohesion: 0.08
Nodes (27): 207(), Ag(), appendChild(), applyValueToInputSignal(), Ay(), clear(), copyText(), dispatchEvent() (+19 more)

### Community 34 - "Fac Withconfig"
Cohesion: 0.04
Nodes (66): ao(), appendAll(), areAllVisibleConfluenceSpacesSelected(), attachToAppRef(), bb(), bf(), $c(), Cc() (+58 more)

### Community 35 - "Core Group 35"
Cohesion: 0.04
Nodes (80): i1(), o1(), xT(), Yh(), al(), Bi(), Br(), buildHeaders() (+72 more)

### Community 36 - "Allcontrolsdisabled Applyformstate"
Cohesion: 0.13
Nodes (22): _applyFormState(), _cancelExistingSubscription(), disable(), enable(), _forEachChild(), markAllAsDirty(), markAllAsTouched(), markAsDirty() (+14 more)

### Community 37 - "Test Confluence"
Cohesion: 0.11
Nodes (6): ConfluenceIntegrationController, AtlassianConnection, ConfluenceApiService, Illuminate\Http\Client\PendingRequest, Illuminate\Http\Client\Response, ConfluenceIntegrationTest

### Community 38 - "Widgetchatcontroller Projectcontroller"
Cohesion: 0.12
Nodes (5): WidgetChatController, ChatSession, Carbon\CarbonInterface, Illuminate\Database\Eloquent\Relations\HasOne, ProjectChatTest

### Community 39 - "Changeownpassword Clearstoreduser"
Cohesion: 0.12
Nodes (28): ak(), ck(), closeModal(), d2(), dO(), documentsTotalPages(), eN(), goToDocumentsPage() (+20 more)

### Community 40 - "Component Theme"
Cohesion: 0.09
Nodes (13): Theme, ThemeService, Injectable, AppShellComponent, Component, HeaderComponent, Component, Input (+5 more)

### Community 42 - "Attach Clone"
Cohesion: 0.08
Nodes (30): Ac(), Ol(), attach(), bs(), create(), detach(), df(), Ds() (+22 more)

### Community 43 - "Component Page"
Cohesion: 0.14
Nodes (6): countryLabel(), Country, Tenant, TenantsPageComponent, Component, roles

### Community 44 - "Component Chatmessage"
Cohesion: 0.19
Nodes (3): ChatSession, ProjectChatPageComponent, Component

### Community 45 - "Attachview Compilemoduleandallcomponentsasync"
Cohesion: 0.12
Nodes (24): Aa(), av(), blankForm(), co(), e0(), Fc(), gy(), i0() (+16 more)

### Community 46 - "Construct Php"
Cohesion: 0.17
Nodes (22): a_(), BM(), capture(), consumeOptional(), match(), noMatchError(), parse(), parseChildren() (+14 more)

### Community 47 - "Angular Cli"
Cohesion: 0.09
Nodes (23): @angular/build, @angular/cli, @angular/compiler-cli, devDependencies, @angular/build, @angular/cli, @angular/compiler-cli, jsdom (+15 more)

### Community 48 - "Component Overview"
Cohesion: 0.16
Nodes (3): Project, ProjectsPageComponent, Component

### Community 49 - "Capture Consumeoptional"
Cohesion: 0.11
Nodes (4): append(), hh(), Pr(), Th()

### Community 50 - "Angular Common"
Cohesion: 0.10
Nodes (21): @angular/common, @angular/compiler, @angular/elements, @angular/forms, @angular/platform-browser, @angular/router, @angular/ssr, dependencies (+13 more)

### Community 51 - "Vite Tailwindcss"
Cohesion: 0.10
Nodes (19): axios, concurrently, laravel-vite-plugin, devDependencies, axios, concurrently, laravel-vite-plugin, tailwindcss (+11 more)

### Community 52 - "Element Domain"
Cohesion: 0.15
Nodes (15): ApiItemResponse, ApiListResponse, ApiPaginatedResponse, ChatMessage, Citation, ConfluenceSpace, CrawledUrl, DocumentChunkPreview (+7 more)

### Community 53 - "Construct Contextretrievalservice"
Cohesion: 0.17
Nodes (3): ContextRetrievalService, OllamaEmbeddingService, OllamaGenerationService

### Community 54 - "Server Routes"
Cohesion: 0.09
Nodes (16): App, appConfig, config, serverConfig, routes, serverRoutes, Component, AuthResponse (+8 more)

### Community 55 - "Activate Activatechildroutes"
Cohesion: 0.11
Nodes (21): bh(), af(), applyPrimaryColor(), Cs(), defaultSettings(), destroy(), ec(), getSystemTheme() (+13 more)

### Community 56 - "Chateventhandlers Reverbservice"
Cohesion: 0.21
Nodes (3): ChatEventHandlers, ReverbService, Injectable

### Community 57 - "Createsnapshot Expandsegmentagainstrouteusingredirect"
Cohesion: 0.13
Nodes (10): activate(), activateChildRoutes(), activateRoutes(), ap(), deactivateChildRoutes(), deactivateRouteAndItsChildren(), deactivateRouteAndOutlet(), deactivateRoutes() (+2 more)

### Community 58 - "Detectcontenttypeheader Addeventlistener"
Cohesion: 0.11
Nodes (7): _assignValidators(), em(), fA(), Gh(), path(), Q0(), _setValidators()

### Community 59 - "Angular Shell"
Cohesion: 0.15
Nodes (17): Self Hosted Deploy Job, Project Conventions Guide, Graphify Query-First Rule, Confluence Ingestion Flow, RAG Queue Sync Mechanism, Angular CLI Workflow, Angular App Root Shell, Zetes Chat Embed Configuration (+9 more)

### Community 60 - "Php Authorizetelescopeaccess"
Cohesion: 0.16
Nodes (8): AuthorizeTelescopeAccess, ValidateTwilioRequest, ValidateWidgetRequest, TelescopeServiceProvider, Closure, Illuminate\Foundation\Configuration\Middleware, Laravel\Telescope\TelescopeApplicationServiceProvider, Symfony\Component\HttpFoundation\Response

### Community 61 - "Appendchild Applystyles"
Cohesion: 0.16
Nodes (19): bR(), chatChannelLabel(), chatDisplaySubtitle(), chatDisplayTimestamp(), chatDisplayTitle(), closePreviewModal(), crawledUrlsTotalPages(), eR() (+11 more)

### Community 62 - "Path Getpath"
Cohesion: 0.11
Nodes (4): ds(), keys(), nb(), toString()

### Community 63 - "Crawledurlstotalpages Gotocrawledurlspage"
Cohesion: 0.16
Nodes (7): decoratePreventDefault(), getBaseHref(), getGlobalEventTarget(), hn(), listen(), onAndCancel(), runGuarded()

### Community 65 - "Php Artisan"
Cohesion: 0.13
Nodes (15): scripts, post-autoload-dump, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall (+7 more)

### Community 66 - "Composer Json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

### Community 67 - "Detectchanges Cleanup"
Cohesion: 0.22
Nodes (13): a2(), An(), cancelEdit(), k2(), labelCountry(), q(), q2(), qn() (+5 more)

### Community 70 - "Styles Browser"
Cohesion: 0.15
Nodes (13): options, allowedCommonJsDependencies, assets, baseHref, browser, outputPath, styles, tsConfig (+5 more)

### Community 71 - "Manageduser Component"
Cohesion: 0.21
Nodes (3): ManagedUser, Component, UsersPageComponent

### Community 72 - "Checkforerrors Checkname"
Cohesion: 0.27
Nodes (3): VoiceSettings, ProjectPhonePageComponent, Component

### Community 73 - "Laravel Ext"
Cohesion: 0.15
Nodes (13): require, ext-dom, ext-libxml, laravel/framework, laravel/reverb, laravel/sanctum, laravel/telescope, laravel/tinker (+5 more)

### Community 74 - "Committransition Createbrowserpath"
Cohesion: 0.36
Nodes (3): Cn(), Ji(), wg()

### Community 76 - "Php Documentparseexception"
Cohesion: 0.21
Nodes (5): DocumentParseException, UnsupportedDocumentTypeException, TwilioNumberService, RuntimeException, Twilio\Rest\Client

### Community 77 - "Illuminate Projectchatmessagecreated"
Cohesion: 0.29
Nodes (5): ProjectChatMessageCreated, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Contracts\Broadcasting\ShouldBroadcastNow, Illuminate\Foundation\Events\Dispatchable, Illuminate\Queue\SerializesModels

### Community 78 - "Parse Supports"
Cohesion: 0.27
Nodes (3): ExcelParser, HtmlParser, DocumentParserInterface

### Community 79 - "Component Widgetsettings"
Cohesion: 0.33
Nodes (3): WidgetSettings, ProjectWidgetPageComponent, Component

### Community 80 - "Afterrun Bootstrap"
Cohesion: 0.29
Nodes (8): documentStatusEntries(), isSupportedUploadFile(), lR(), onDragOver(), onDrop(), onFileSelected(), setSelectedUploadFile(), Zn()

### Community 83 - "Build Builder"
Cohesion: 0.22
Nodes (9): build, builder, defaultConfiguration, widget, architect, prefix, projectType, root (+1 more)

### Community 84 - "Build Scripts"
Cohesion: 0.22
Nodes (9): scripts, build, build:all, build:widget, ng, serve:ssr:frontend, start, test (+1 more)

### Community 85 - "Tsconfig Widget"
Cohesion: 0.22
Nodes (8): compilerOptions, outDir, exclude, extends, include, src/**/*.spec.ts, src/widget/**/*.ts, ./tsconfig.json

### Community 86 - "Registeronvalidatorchange Domain"
Cohesion: 0.47
Nodes (6): dE(), detectContentTypeHeader(), Kf(), Qf(), serializeBody(), Yf()

### Community 87 - "Docxparser Phpoffice"
Cohesion: 0.39
Nodes (3): DocxParser, PhpOffice\PhpWord\Element\AbstractElement, PhpOffice\PhpWord\Element\Table

### Community 89 - "Install Php"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 92 - "Pestphp Pest"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 93 - "Laravel Mockery"
Cohesion: 0.29
Nodes (7): require-dev, fakerphp/faker, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 94 - "Angular Json"
Cohesion: 0.29
Nodes (6): cli, packageManager, newProjectRoot, projects, $schema, version

### Community 95 - "Builder Serve"
Cohesion: 0.29
Nodes (7): serve, test, architect, builder, configurations, defaultConfiguration, builder

### Community 98 - "Userfactory Php"
Cohesion: 0.47
Nodes (3): UserFactory, Illuminate\Database\Eloquent\Factories\Factory, static

### Community 99 - "Configurations Development"
Cohesion: 0.33
Nodes (6): configurations, development, buildTarget, extractLicenses, optimization, sourceMap

### Community 100 - "Prefix Projecttype"
Cohesion: 0.33
Nodes (6): prefix, projectType, root, schematics, sourceRoot, frontend

### Community 103 - "Database Autoload"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 104 - "Databaseseeder Illuminate"
Cohesion: 0.60
Nodes (3): DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 105 - "Production Budgets"
Cohesion: 0.40
Nodes (5): production, budgets, buildTarget, optimization, outputHashing

### Community 106 - "Package Json"
Cohesion: 0.40
Nodes (4): name, packageManager, private, version

### Community 107 - "Server Angularapp"
Cohesion: 0.40
Nodes (4): angularApp, app, browserDistFolder, reqHandler

### Community 109 - "Angular Cli"
Cohesion: 0.50
Nodes (3): angular-cli, npx, @angular/cli

### Community 110 - "Create Telescope"
Cohesion: 0.83
Nodes (3): down(), getConnection(), up()

### Community 113 - "Keywords Framework"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 114 - "Php Artisan"
Cohesion: 0.67
Nodes (3): dev, Composer\\Config::disableProcessTimeout, npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others

### Community 143 - "Cache Php"
Cohesion: 0.08
Nodes (11): TwilioVoiceController, AnswerVoiceTurnJob, PhoneCall, AnswerOptions, ChatAnswerService, VoiceResponseFormatter, VoiceSettings, VoiceTurnStore (+3 more)

## Ambiguous Edges - Review These
- `Zetes Chat Embed Configuration` → `Angular Widget Deployment Shell`  [AMBIGUOUS]
  public/chat.html · relation: references

## Knowledge Gaps
- **190 isolated node(s):** `npx`, `@angular/cli`, `$schema`, `name`, `type` (+185 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **26 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `Zetes Chat Embed Configuration` and `Angular Widget Deployment Shell`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **Why does `t()` connect `Addclass Applyvaluetoinputsignal` to `Rgdfdfoa Addasyncvalidators`, `Addasyncvalidators Addvalidators`, `Canceldelete Canceledit`, `Activatedcomponentref Activatedroute`, `Attachtoappref Createcomponentref`, `Get Appendchild`, `Attach Createcomment`, `Applystyles Applytohost`, `Warn Addclass`, `Addcontrol Setvalue`, `Closepanel Feedbackfor`, `Destroy Adjustindex`, `Constructor Addheaderentry`, `Complete Documentstatusentries`, `Applypaginationmeta Confirmdeletedocument`, `Chatdisplaytimestamp Createtext`, `Appendall Areallvisibleconfluencespacesselected`, `Constructor Assignasyncvalidators`, `Bootstrap Afterpreactivation`, `Attachtoviewcontainerref Chatdisplaysubtitle`, `Fac Withconfig`, `.execute`, `Core Group 35`, `Changeownpassword Clearstoreduser`, `Attach Clone`, `Component Chatmessage`, `Attachview Compilemoduleandallcomponentsasync`, `Construct Php`, `Capture Consumeoptional`, `Activate Activatechildroutes`, `Detectcontenttypeheader Addeventlistener`, `Appendchild Applystyles`, `Path Getpath`, `Crawledurlstotalpages Gotocrawledurlspage`, `Createurltree Hasactivelinks`, `Detectchanges Cleanup`, `Committransition Createbrowserpath`, `Afterrun Bootstrap`?**
  _High betweenness centrality (0.129) - this node is a cross-community bridge._
- **Why does `ProjectChatPageComponent` connect `Component Chatmessage` to `Component Page`, `Element Domain`?**
  _High betweenness centrality (0.128) - this node is a cross-community bridge._
- **Why does `e` connect `Activatedcomponentref Activatedroute` to `Rgdfdfoa Addasyncvalidators`, `Addasyncvalidators Addvalidators`, `Findcontainer Removecontrol`, `Canceldelete Canceledit`, `Getbasehreffromdom Getbasehref`, `Addclass Applyvaluetoinputsignal`, `Angular Platform`, `Attachtoappref Createcomponentref`, `Get Appendchild`, `Attach Createcomment`, `Addcontrol Setvalue`, `Destroy Adjustindex`, `Complete Documentstatusentries`, `Chatdisplaytimestamp Createtext`, `Appendall Areallvisibleconfluencespacesselected`, `Constructor Assignasyncvalidators`, `Bootstrap Afterpreactivation`, `Attachtoviewcontainerref Chatdisplaysubtitle`, `Fac Withconfig`, `.register`, `.execute`, `Core Group 35`, `Changeownpassword Clearstoreduser`, `Component Chatmessage`, `Construct Php`, `Capture Consumeoptional`, `Createsnapshot Expandsegmentagainstrouteusingredirect`, `Detectcontenttypeheader Addeventlistener`, `Appendchild Applystyles`, `Path Getpath`, `Crawledurlstotalpages Gotocrawledurlspage`, `Createurltree Hasactivelinks`, `Confluencedocumenterror Hasconfluencesyncissue`, `Committransition Createbrowserpath`, `Append Head`, `Ngvalue Setelementvalue`?**
  _High betweenness centrality (0.073) - this node is a cross-community bridge._
- **Are the 163 inferred relationships involving `t()` (e.g. with `_1()` and `a_()`) actually correct?**
  _`t()` has 163 INFERRED edges - model-reasoned connections that need verification._
- **Are the 19 inferred relationships involving `u()` (e.g. with `t()` and `g()`) actually correct?**
  _`u()` has 19 INFERRED edges - model-reasoned connections that need verification._
- **What connects `npx`, `@angular/cli`, `$schema` to the rest of the system?**
  _190 weakly-connected nodes found - possible documentation gaps or missing edges._