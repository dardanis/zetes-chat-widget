# Graph Report - zetes-chat-widget  (2026-08-06)

## Corpus Check
- 214 files · ~92,114 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3730 nodes · 10067 edges · 161 communities (135 shown, 26 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 647 edges (avg confidence: 0.69)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `f81d5068`
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
- vy
- o1
- Php Artisan
- Composer Json
- .bootstrapImpl
- q1
- Widget Test
- Styles Browser
- Manageduser Component
- Checkforerrors Checkname
- Laravel Ext
- rr
- Test Parser
- Php Documentparseexception
- Illuminate Projectchatmessagecreated
- Parse Supports
- Component Widgetsettings
- .handleRouterEvent
- mf
- Construct Pdfparser
- Build Builder
- Build Scripts
- Tsconfig Widget
- Docxparser Phpoffice
- Xmlparser Php
- Install Php
- Emit Handleerror
- Checkfinalizedstatuses Error
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
- Database Autoload
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
- @angular/core
- .execute
- np
- Q0

## God Nodes (most connected - your core abstractions)
1. `e` - 300 edges
2. `t()` - 193 edges
3. `e` - 158 edges
4. `u()` - 141 edges
5. `Project` - 83 edges
6. `User` - 78 edges
7. `N()` - 67 edges
8. `constructor()` - 62 edges
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
  public/widget/main.js → public/ng/main-CW5TU5ZB.js
- `Self Hosted Deploy Job` --conceptually_related_to--> `Angular NG Deployment Shell`  [INFERRED]
  .github/workflows/ci-self-hosted.yml → public/ng/index.html

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Widget Runtime Delivery Flow** — readme_rag_mvp_widget_security_controls, public_chat_zetes_chat_embed_configuration, public_widget_index_angular_widget_deployment_shell [INFERRED 0.85]
- **Angular Multi-Target Build Outputs** — frontend_readme_angular_cli_workflow, public_ng_index_angular_ng_deployment_shell, public_widget_index_angular_widget_deployment_shell, public_ng_3rdpartylicenses_third_party_licenses_manifest, public_widget_3rdpartylicenses_third_party_licenses_manifest [INFERRED 0.75]
- **RAG Ingestion and Sync Boundary** — readme_rag_mvp_rag_mvp_architecture, readme_rag_mvp_chunking_strategy_sliding_window, docs_confluence_ingestion_confluence_ingestion_flow, docs_confluence_ingestion_rag_queue_sync_mechanism [INFERRED 0.85]

## Communities (161 total, 26 thin omitted)

### Community 0 - "Rgdfdfoa Addasyncvalidators"
Cohesion: 0.01
Nodes (187): a0(), Ah(), ai(), _allControlsDisabled(), aM(), _anyControls(), _anyControlsDirty(), _anyControlsHaveStatus() (+179 more)

### Community 1 - "Addasyncvalidators Addvalidators"
Cohesion: 0.02
Nodes (153): cE(), add(), addAsyncValidators(), addClass(), _addParent(), addValidators(), _anyControls(), _anyControlsDirty() (+145 more)

### Community 2 - "Registeronchange Subscribe"
Cohesion: 0.02
Nodes (35): iM(), vf(), afterRun(), append(), appendChild(), attachToAppRef(), cr(), cv() (+27 more)

### Community 3 - "Canceldelete Canceledit"
Cohesion: 0.02
Nodes (9): cA(), deactivateRouteAndOutlet(), e, eb(), getBaseHref(), Jb(), path(), registerOnChange() (+1 more)

### Community 4 - "Activatedcomponentref Activatedroute"
Cohesion: 0.06
Nodes (131): _2(), a_(), a2(), ak(), An(), ar(), b2(), bk() (+123 more)

### Community 5 - "Addclass Applyvaluetoinputsignal"
Cohesion: 0.06
Nodes (60): ae(), assignPhoneNumber(), be(), bootstrap(), changeOwnPassword(), clearStoredUser(), cp(), crawlWebsite() (+52 more)

### Community 6 - "Php Destroy"
Cohesion: 0.08
Nodes (14): AccountController, UserController, AuthenticatedSessionController, ConfluenceIntegrationController, Controller, CountryController, OllamaProxyController, ProjectDocumentController (+6 more)

### Community 7 - "Php Test"
Cohesion: 0.05
Nodes (16): ChatMessage, ChatMessageFeedback, Country, DocumentChunk, MessageCitation, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Database\Eloquent\Relations\HasMany (+8 more)

### Community 8 - "Test User"
Cohesion: 0.06
Nodes (14): Tenant, User, AccessControlService, Illuminate\Database\Eloquent\Builder, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable (+6 more)

### Community 9 - "Attachtoappref Createcomponentref"
Cohesion: 0.04
Nodes (85): _1(), a1(), ab(), addMatch(), Af(), aS(), av(), b0() (+77 more)

### Community 10 - "Get Appendchild"
Cohesion: 0.07
Nodes (41): fh(), gT(), Qh(), tM(), vT(), Am(), as(), ay() (+33 more)

### Community 11 - "Php Construct"
Cohesion: 0.07
Nodes (14): CrawlProjectWebsiteJob, EmbedDocumentChunkJob, ProcessProjectDocumentJob, ResyncConfluenceDocumentJob, SyncProjectConfluenceSpaceJob, ProjectConfluenceSpace, DocumentChunkingService, DocumentParserRegistry (+6 more)

### Community 12 - "Attach Createcomment"
Cohesion: 0.06
Nodes (56): applyPaginationMeta(), b_(), clearTimeout(), confirmDeleteDocument(), connectAndLoadConfluenceSpaces(), createChat(), d1(), encodeKey() (+48 more)

### Community 13 - "Applystyles Applytohost"
Cohesion: 0.06
Nodes (38): activate(), activateChildRoutes(), activateRoutes(), appendAll(), bb(), ci(), clone(), copyFrom() (+30 more)

### Community 14 - "Atlassianconnection Paginationmeta"
Cohesion: 0.07
Nodes (6): AtlassianConnection, PaginationMeta, ProjectConfluenceSpace, ProjectDocument, ProjectDocumentsPageComponent, Component

### Community 15 - "Warn Addclass"
Cohesion: 0.08
Nodes (36): runTask(), aa(), ad(), be(), bo(), ca(), cd(), cg() (+28 more)

### Community 17 - "Component Page"
Cohesion: 0.08
Nodes (14): authGuard(), guestGuard(), ProjectLayoutComponent, Component, DashboardPageComponent, Component, LoginPageComponent, Component (+6 more)

### Community 18 - "Closepanel Feedbackfor"
Cohesion: 0.06
Nodes (69): 594(), A(), ai(), Ao(), bf(), bn(), _c(), cc() (+61 more)

### Community 19 - "Destroy Adjustindex"
Cohesion: 0.15
Nodes (14): ap(), attachToViewContainerRef(), deactivateChildRoutes(), deactivateRouteAndItsChildren(), deactivateRoutes(), detachAndStoreRouteSubtree(), insert(), insertImpl() (+6 more)

### Community 20 - "Constructor Addheaderentry"
Cohesion: 0.04
Nodes (66): Ol(), qo(), addHeaderEntry(), aE(), appendAll(), applyUpdate(), _assignAsyncValidators(), _assignValidators() (+58 more)

### Community 21 - "Component Createsession"
Cohesion: 0.07
Nodes (12): Component, Input, WidgetTheme, ZetesChatComponent, ChatMessage, Citation, CreateSessionResponse, SendMessageResponse (+4 more)

### Community 23 - "Remove Unsubscribe"
Cohesion: 0.04
Nodes (70): ad(), add(), _addParent(), applyUpdate(), Bd(), bi(), _d(), detectChanges() (+62 more)

### Community 25 - "Complete Documentstatusentries"
Cohesion: 0.05
Nodes (51): _0(), addClass(), _adjustIndex(), b1(), bindRealtime(), c0(), c1(), destroy() (+43 more)

### Community 26 - "Applypaginationmeta Confirmdeletedocument"
Cohesion: 0.09
Nodes (33): Cf(), di(), Ed(), eo(), eS(), fp(), gc(), injectableDefInScope() (+25 more)

### Community 27 - "Chatdisplaytimestamp Createtext"
Cohesion: 0.13
Nodes (18): bh(), af(), applyPrimaryColor(), defaultSettings(), getSystemTheme(), hu(), ju(), loadSettings() (+10 more)

### Community 28 - "Appendall Areallvisibleconfluencespacesselected"
Cohesion: 0.10
Nodes (21): Aa(), addControl(), _cancelExistingSubscription(), fA(), iA(), markAllAsDirty(), markAsDirty(), patchValue() (+13 more)

### Community 30 - "Bootstrap Afterpreactivation"
Cohesion: 0.11
Nodes (14): applyStyles(), applyToHost(), bA(), dd(), Gb(), jD(), pd(), r1() (+6 more)

### Community 31 - "Test Document"
Cohesion: 0.06
Nodes (30): 10. Phase 8 — Tests, 11. Sequencing and effort, 11b. Measured latency (2026-08-03, this hardware), 12. Risks, 13. Open items for later, 1. Architecture, 2. Phase 0 — Prerequisites, 3. Phase 1 — Data model (+22 more)

### Community 32 - "Getuseragent Producermustrecompute"
Cohesion: 0.06
Nodes (49): Ba(), Bg(), by(), cf(), cm(), Cs(), cu(), destroy() (+41 more)

### Community 33 - "Attachtoviewcontainerref Chatdisplaysubtitle"
Cohesion: 0.30
Nodes (15): capture(), consumeOptional(), parse(), parseChildren(), parseFragment(), parseMatrixParams(), parseParam(), parseParens() (+7 more)

### Community 34 - "Fac Withconfig"
Cohesion: 0.08
Nodes (46): bg(), Bt(), cg(), De(), dg(), ec(), eg(), et() (+38 more)

### Community 35 - "Core Group 35"
Cohesion: 0.06
Nodes (57): mE(), rp(), xT(), Yh(), al(), Br(), buildHeaders(), cl() (+49 more)

### Community 36 - "Allcontrolsdisabled Applyformstate"
Cohesion: 0.10
Nodes (29): _allControlsDisabled(), _applyFormState(), asyncValidator(), _calculateStatus(), _cancelExistingSubscription(), disable(), enable(), _forEachChild() (+21 more)

### Community 37 - "Test Confluence"
Cohesion: 0.30
Nodes (3): ConfluenceApiService, Illuminate\Http\Client\PendingRequest, Illuminate\Http\Client\Response

### Community 39 - "Changeownpassword Clearstoreduser"
Cohesion: 0.10
Nodes (20): ag(), applyStyles(), applyToHost(), bu(), emit(), handleError(), hh(), jh() (+12 more)

### Community 40 - "Component Theme"
Cohesion: 0.09
Nodes (13): Theme, ThemeService, Injectable, AppShellComponent, Component, HeaderComponent, Component, Input (+5 more)

### Community 41 - "Test User"
Cohesion: 0.10
Nodes (4): ProjectController, Project, self, ProjectManagementTest

### Community 42 - "Attach Clone"
Cohesion: 0.10
Nodes (26): Ac(), ao(), attachToAppRef(), bf(), $c(), Cc(), cd(), cy() (+18 more)

### Community 43 - "Component Page"
Cohesion: 0.14
Nodes (6): countryLabel(), Country, Tenant, TenantsPageComponent, Component, roles

### Community 44 - "Component Chatmessage"
Cohesion: 0.19
Nodes (3): ChatSession, ProjectChatPageComponent, Component

### Community 46 - "Construct Php"
Cohesion: 0.07
Nodes (37): Ar(), at(), au(), Bd(), bm(), dm(), Ea(), fm() (+29 more)

### Community 47 - "Angular Cli"
Cohesion: 0.09
Nodes (23): @angular/build, @angular/cli, @angular/compiler-cli, devDependencies, @angular/build, @angular/cli, @angular/compiler-cli, jsdom (+15 more)

### Community 48 - "Component Overview"
Cohesion: 0.16
Nodes (3): Project, ProjectsPageComponent, Component

### Community 49 - "Capture Consumeoptional"
Cohesion: 0.14
Nodes (6): detectContentTypeHeader(), getGlobalEventTarget(), onAndCancel(), runGuarded(), runOutsideAngular(), vM()

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
Cohesion: 0.26
Nodes (4): WarmVoiceModelsCommand, OllamaEmbeddingService, OllamaGenerationService, Illuminate\Console\Command

### Community 54 - "Server Routes"
Cohesion: 0.09
Nodes (16): App, appConfig, config, serverConfig, routes, serverRoutes, Component, AuthResponse (+8 more)

### Community 56 - "Chateventhandlers Reverbservice"
Cohesion: 0.21
Nodes (3): ChatEventHandlers, ReverbService, Injectable

### Community 57 - "Createsnapshot Expandsegmentagainstrouteusingredirect"
Cohesion: 0.18
Nodes (4): append(), hh(), Pr(), Th()

### Community 59 - "Angular Shell"
Cohesion: 0.15
Nodes (17): Self Hosted Deploy Job, Project Conventions Guide, Graphify Query-First Rule, Confluence Ingestion Flow, RAG Queue Sync Mechanism, Angular CLI Workflow, Angular App Root Shell, Zetes Chat Embed Configuration (+9 more)

### Community 60 - "Php Authorizetelescopeaccess"
Cohesion: 0.16
Nodes (8): AuthorizeTelescopeAccess, ValidateTwilioRequest, ValidateWidgetRequest, TelescopeServiceProvider, Closure, Illuminate\Foundation\Configuration\Middleware, Laravel\Telescope\TelescopeApplicationServiceProvider, Symfony\Component\HttpFoundation\Response

### Community 63 - "vy"
Cohesion: 0.09
Nodes (27): hc(), og(), attach(), av(), create(), detach(), df(), du() (+19 more)

### Community 64 - "o1"
Cohesion: 0.07
Nodes (36): i1(), o1(), Ah(), bh(), bt(), ed(), _find(), get() (+28 more)

### Community 65 - "Php Artisan"
Cohesion: 0.13
Nodes (15): scripts, post-autoload-dump, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall (+7 more)

### Community 66 - "Composer Json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

### Community 67 - ".bootstrapImpl"
Cohesion: 0.05
Nodes (42): 207(), Ag(), appendChild(), applyValueToInputSignal(), attach(), Ay(), ch(), clear() (+34 more)

### Community 68 - "q1"
Cohesion: 0.10
Nodes (29): bc(), Bv(), co(), cv(), Dc(), dv(), e0(), Fc() (+21 more)

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

### Community 76 - "Php Documentparseexception"
Cohesion: 0.14
Nodes (4): TwilioNumberService, TwilioNumberServiceTest, Twilio\Base\PhoneNumberCapabilities, Twilio\Rest\Client

### Community 77 - "Illuminate Projectchatmessagecreated"
Cohesion: 0.29
Nodes (5): ProjectChatMessageCreated, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Contracts\Broadcasting\ShouldBroadcastNow, Illuminate\Foundation\Events\Dispatchable, Illuminate\Queue\SerializesModels

### Community 78 - "Parse Supports"
Cohesion: 0.27
Nodes (3): ExcelParser, HtmlParser, DocumentParserInterface

### Community 79 - "Component Widgetsettings"
Cohesion: 0.33
Nodes (3): WidgetSettings, ProjectWidgetPageComponent, Component

### Community 80 - ".handleRouterEvent"
Cohesion: 0.31
Nodes (7): complete(), error(), ld(), md(), next(), rg(), Ua()

### Community 83 - "Build Builder"
Cohesion: 0.22
Nodes (9): build, builder, defaultConfiguration, widget, architect, prefix, projectType, root (+1 more)

### Community 84 - "Build Scripts"
Cohesion: 0.22
Nodes (9): scripts, build, build:all, build:widget, ng, serve:ssr:frontend, start, test (+1 more)

### Community 85 - "Tsconfig Widget"
Cohesion: 0.22
Nodes (8): compilerOptions, outDir, exclude, extends, include, src/**/*.spec.ts, src/widget/**/*.ts, ./tsconfig.json

### Community 87 - "Docxparser Phpoffice"
Cohesion: 0.39
Nodes (3): DocxParser, PhpOffice\PhpWord\Element\AbstractElement, PhpOffice\PhpWord\Element\Table

### Community 89 - "Install Php"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 90 - "Emit Handleerror"
Cohesion: 0.60
Nodes (3): DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 91 - "Checkfinalizedstatuses Error"
Cohesion: 0.32
Nodes (3): DocumentParseException, UnsupportedDocumentTypeException, RuntimeException

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

### Community 105 - "Production Budgets"
Cohesion: 0.40
Nodes (5): production, budgets, buildTarget, optimization, outputHashing

### Community 106 - "Package Json"
Cohesion: 0.40
Nodes (4): name, packageManager, private, version

### Community 107 - "Server Angularapp"
Cohesion: 0.40
Nodes (4): angularApp, app, browserDistFolder, reqHandler

### Community 108 - "Ngvalue Setelementvalue"
Cohesion: 0.08
Nodes (8): ProjectAnalyticsController, ProjectChatController, WidgetChatController, ChatSession, ChatAnswerService, ProjectAccessService, Carbon\CarbonInterface, Illuminate\Database\Eloquent\Relations\HasOne

### Community 109 - "Angular Cli"
Cohesion: 0.50
Nodes (3): angular-cli, npx, @angular/cli

### Community 110 - "Create Telescope"
Cohesion: 0.83
Nodes (3): down(), getConnection(), up()

### Community 112 - "Validatecsrftoken Php"
Cohesion: 0.25
Nodes (3): ValidateCsrfToken, Illuminate\Foundation\Http\Middleware\ValidateCsrfToken, CsrfExclusionTest

### Community 113 - "Keywords Framework"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 114 - "Php Artisan"
Cohesion: 0.67
Nodes (3): dev, Composer\\Config::disableProcessTimeout, npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others

### Community 143 - "Cache Php"
Cohesion: 0.08
Nodes (9): ProjectVoiceController, TwilioVoiceController, AnswerVoiceTurnJob, PhoneCall, VoiceResponseFormatter, VoiceSettings, VoiceTurnStore, Illuminate\Http\Response (+1 more)

### Community 165 - ".execute"
Cohesion: 0.13
Nodes (11): addHeaderEntry(), afterRun(), bp(), constructor(), D0(), flush(), flushQueue(), maybeSetNormalizedName() (+3 more)

### Community 167 - "np"
Cohesion: 0.13
Nodes (22): cb(), createSnapshot(), expandSegmentAgainstRouteUsingRedirect(), fb(), getChildConfig(), GM(), hasChildren(), matchSegmentAgainstRoute() (+14 more)

### Community 170 - "Q0"
Cohesion: 0.12
Nodes (10): _assignAsyncValidators(), Gh(), Ji(), no(), Q0(), _setAsyncValidators(), TA(), vd() (+2 more)

## Ambiguous Edges - Review These
- `Zetes Chat Embed Configuration` → `Angular Widget Deployment Shell`  [AMBIGUOUS]
  public/chat.html · relation: references

## Knowledge Gaps
- **191 isolated node(s):** `npx`, `@angular/cli`, `$schema`, `name`, `type` (+186 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **26 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `Zetes Chat Embed Configuration` and `Angular Widget Deployment Shell`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **Why does `t()` connect `Attach Createcomment` to `Rgdfdfoa Addasyncvalidators`, `Addasyncvalidators Addvalidators`, `Canceldelete Canceledit`, `Activatedcomponentref Activatedroute`, `Addclass Applyvaluetoinputsignal`, `Attachtoappref Createcomponentref`, `Get Appendchild`, `Applystyles Applytohost`, `Warn Addclass`, `Closepanel Feedbackfor`, `Destroy Adjustindex`, `Constructor Addheaderentry`, `Remove Unsubscribe`, `Complete Documentstatusentries`, `Applypaginationmeta Confirmdeletedocument`, `Chatdisplaytimestamp Createtext`, `Appendall Areallvisibleconfluencespacesselected`, `Bootstrap Afterpreactivation`, `Getuseragent Producermustrecompute`, `Fac Withconfig`, `Core Group 35`, `.execute`, `np`, `Attach Clone`, `Q0`, `Component Chatmessage`, `Construct Php`, `Createsnapshot Expandsegmentagainstrouteusingredirect`, `vy`, `o1`, `.bootstrapImpl`, `q1`, `.handleRouterEvent`?**
  _High betweenness centrality (0.148) - this node is a cross-community bridge._
- **Why does `ProjectChatPageComponent` connect `Component Chatmessage` to `Component Page`, `Element Domain`?**
  _High betweenness centrality (0.138) - this node is a cross-community bridge._
- **Why does `e` connect `Canceldelete Canceledit` to `Rgdfdfoa Addasyncvalidators`, `Addasyncvalidators Addvalidators`, `Activatedcomponentref Activatedroute`, `Addclass Applyvaluetoinputsignal`, `Attachtoappref Createcomponentref`, `Get Appendchild`, `Attach Createcomment`, `Applystyles Applytohost`, `Destroy Adjustindex`, `Remove Unsubscribe`, `Complete Documentstatusentries`, `Applypaginationmeta Confirmdeletedocument`, `Appendall Areallvisibleconfluencespacesselected`, `Bootstrap Afterpreactivation`, `Fac Withconfig`, `Core Group 35`, `.execute`, `Q0`, `Attach Clone`, `Component Chatmessage`, `Capture Consumeoptional`, `Createsnapshot Expandsegmentagainstrouteusingredirect`, `.bootstrapImpl`, `q1`, `.handleRouterEvent`?**
  _High betweenness centrality (0.081) - this node is a cross-community bridge._
- **Are the 159 inferred relationships involving `t()` (e.g. with `_1()` and `Ah()`) actually correct?**
  _`t()` has 159 INFERRED edges - model-reasoned connections that need verification._
- **Are the 19 inferred relationships involving `u()` (e.g. with `t()` and `g()`) actually correct?**
  _`u()` has 19 INFERRED edges - model-reasoned connections that need verification._
- **What connects `npx`, `@angular/cli`, `$schema` to the rest of the system?**
  _191 weakly-connected nodes found - possible documentation gaps or missing edges._