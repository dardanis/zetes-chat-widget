# Graph Report - zetes-chat-widget  (2026-08-06)

## Corpus Check
- 212 files · ~90,764 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3707 nodes · 9997 edges · 154 communities (129 shown, 25 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 644 edges (avg confidence: 0.69)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `e7a56b1a`
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
- Php Artisan
- Composer Json
- Widget Test
- Styles Browser
- Manageduser Component
- Checkforerrors Checkname
- Laravel Ext
- Test Parser
- Php Documentparseexception
- Illuminate Projectchatmessagecreated
- Parse Supports
- Component Widgetsettings
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
- @angular/core
- np

## God Nodes (most connected - your core abstractions)
1. `e` - 300 edges
2. `t()` - 193 edges
3. `e` - 158 edges
4. `u()` - 141 edges
5. `Project` - 79 edges
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

## Communities (154 total, 25 thin omitted)

### Community 0 - "Rgdfdfoa Addasyncvalidators"
Cohesion: 0.01
Nodes (185): a0(), Aa(), add(), addHeaderEntry(), _addParent(), Ah(), _allControlsDisabled(), _anyControls() (+177 more)

### Community 1 - "Addasyncvalidators Addvalidators"
Cohesion: 0.02
Nodes (157): bh(), cE(), runTask(), add(), addAsyncValidators(), addClass(), _addParent(), addValidators() (+149 more)

### Community 2 - "Registeronchange Subscribe"
Cohesion: 0.02
Nodes (36): hc(), iM(), og(), qo(), vf(), afterRun(), append(), appendChild() (+28 more)

### Community 3 - "Canceldelete Canceledit"
Cohesion: 0.02
Nodes (10): append(), Bd(), cv(), e, eb(), getBaseHref(), hh(), path() (+2 more)

### Community 4 - "Activatedcomponentref Activatedroute"
Cohesion: 0.06
Nodes (126): _2(), a_(), a2(), ak(), An(), ar(), b2(), bk() (+118 more)

### Community 5 - "Addclass Applyvaluetoinputsignal"
Cohesion: 0.07
Nodes (67): ae(), assignPhoneNumber(), be(), bootstrap(), changeOwnPassword(), clearStoredUser(), cp(), crawlWebsite() (+59 more)

### Community 6 - "Php Destroy"
Cohesion: 0.05
Nodes (17): AccountController, UserController, AuthenticatedSessionController, ConfluenceIntegrationController, Controller, CountryController, OllamaProxyController, ProjectChatController (+9 more)

### Community 7 - "Php Test"
Cohesion: 0.05
Nodes (12): DocumentChunk, MessageCitation, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, RegistrationTest, ExampleTest (+4 more)

### Community 8 - "Test User"
Cohesion: 0.06
Nodes (16): Project, Tenant, User, AccessControlService, Illuminate\Database\Eloquent\Builder, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Foundation\Auth\User (+8 more)

### Community 9 - "Attachtoappref Createcomponentref"
Cohesion: 0.05
Nodes (70): addMatch(), aS(), av(), b0(), createText(), cs(), Df(), di() (+62 more)

### Community 10 - "Get Appendchild"
Cohesion: 0.07
Nodes (41): fh(), gT(), Qh(), tM(), vT(), as(), B(), co() (+33 more)

### Community 11 - "Php Construct"
Cohesion: 0.08
Nodes (12): CrawlProjectWebsiteJob, EmbedDocumentChunkJob, ProcessProjectDocumentJob, ResyncConfluenceDocumentJob, SyncProjectConfluenceSpaceJob, ProjectConfluenceSpace, DocumentChunkingService, ParsedDocumentAdapter (+4 more)

### Community 12 - "Attach Createcomment"
Cohesion: 0.07
Nodes (52): applyPaginationMeta(), bp(), clearTimeout(), confirmDeleteDocument(), connectAndLoadConfluenceSpaces(), createChat(), d1(), find() (+44 more)

### Community 13 - "Applystyles Applytohost"
Cohesion: 0.06
Nodes (39): a1(), ap(), appendAll(), areAllVisibleConfluenceSpacesSelected(), Bv(), clone(), delete(), dM() (+31 more)

### Community 14 - "Atlassianconnection Paginationmeta"
Cohesion: 0.07
Nodes (9): AtlassianConnection, ConfluenceSpace, CrawledUrl, DocumentChunkPreview, PaginationMeta, ProjectConfluenceSpace, ProjectDocument, ProjectDocumentsPageComponent (+1 more)

### Community 15 - "Warn Addclass"
Cohesion: 0.04
Nodes (76): Am(), applyStyles(), applyToHost(), at(), attach(), au(), av(), Bd() (+68 more)

### Community 16 - "Addcontrol Setvalue"
Cohesion: 0.05
Nodes (55): _1(), Af(), appendChild(), attach(), attachToViewContainerRef(), bc(), bs(), closeCreateModal() (+47 more)

### Community 17 - "Component Page"
Cohesion: 0.08
Nodes (17): authGuard(), guestGuard(), AuthResponse, LoginPayload, RegisterPayload, User, DashboardStats, ProjectLayoutComponent (+9 more)

### Community 18 - "Closepanel Feedbackfor"
Cohesion: 0.05
Nodes (77): 207(), 594(), i1(), o1(), xT(), Yh(), A(), ai() (+69 more)

### Community 19 - "Destroy Adjustindex"
Cohesion: 0.05
Nodes (28): ab(), afterRun(), aM(), _assignAsyncValidators(), bb(), copyFrom(), D0(), decodeKey() (+20 more)

### Community 20 - "Constructor Addheaderentry"
Cohesion: 0.04
Nodes (69): Ac(), Ol(), addHeaderEntry(), aE(), appendAll(), applyUpdate(), _assignValidators(), ay() (+61 more)

### Community 21 - "Component Createsession"
Cohesion: 0.07
Nodes (12): Component, Input, WidgetTheme, ZetesChatComponent, ChatMessage, Citation, CreateSessionResponse, SendMessageResponse (+4 more)

### Community 23 - "Remove Unsubscribe"
Cohesion: 0.07
Nodes (46): ad(), bi(), Bn(), _d(), detectChanges(), ea(), Ff(), Gf() (+38 more)

### Community 25 - "Complete Documentstatusentries"
Cohesion: 0.06
Nodes (41): _0(), addClass(), _adjustIndex(), bindRealtime(), c0(), c1(), clear(), copyEmbedCode() (+33 more)

### Community 26 - "Applypaginationmeta Confirmdeletedocument"
Cohesion: 0.09
Nodes (35): Ay(), co(), Dc(), dv(), e0(), ee(), _f(), Fc() (+27 more)

### Community 27 - "Chatdisplaytimestamp Createtext"
Cohesion: 0.07
Nodes (40): ao(), attachToAppRef(), b1(), $c(), cd(), Cf(), cy(), dy() (+32 more)

### Community 28 - "Appendall Areallvisibleconfluencespacesselected"
Cohesion: 0.09
Nodes (29): addControl(), ai(), _applyFormState(), _cancelExistingSubscription(), disable(), enable(), fA(), _forEachChild() (+21 more)

### Community 29 - "Constructor Assignasyncvalidators"
Cohesion: 0.12
Nodes (15): activate(), activateChildRoutes(), activateRoutes(), ci(), deactivateChildRoutes(), deactivateRouteAndItsChildren(), deactivateRouteAndOutlet(), deactivateRoutes() (+7 more)

### Community 30 - "Bootstrap Afterpreactivation"
Cohesion: 0.09
Nodes (10): bA(), ds(), Gb(), jv(), nb(), _registerOnDestroy(), registerOnDisabledChange(), setStyle() (+2 more)

### Community 31 - "Test Document"
Cohesion: 0.06
Nodes (30): 10. Phase 8 — Tests, 11. Sequencing and effort, 11b. Measured latency (2026-08-03, this hardware), 12. Risks, 13. Open items for later, 1. Architecture, 2. Phase 0 — Prerequisites, 3. Phase 1 — Data model (+22 more)

### Community 32 - "Getuseragent Producermustrecompute"
Cohesion: 0.06
Nodes (46): aa(), Ba(), Bg(), bo(), by(), cm(), createComment(), dispatchEvent() (+38 more)

### Community 33 - "Attachtoviewcontainerref Chatdisplaysubtitle"
Cohesion: 0.17
Nodes (23): BM(), capture(), consumeOptional(), iy(), lp(), match(), noMatchError(), parse() (+15 more)

### Community 34 - "Fac Withconfig"
Cohesion: 0.07
Nodes (51): bf(), bg(), Bt(), Cc(), cg(), chatDisplaySubtitle(), constructor(), De() (+43 more)

### Community 35 - "Core Group 35"
Cohesion: 0.06
Nodes (59): mE(), rp(), al(), Br(), buildHeaders(), cl(), createSession(), dl() (+51 more)

### Community 36 - "Allcontrolsdisabled Applyformstate"
Cohesion: 0.08
Nodes (32): _allControlsDisabled(), _applyFormState(), asyncValidator(), _calculateStatus(), _cancelExistingSubscription(), cr(), disable(), enable() (+24 more)

### Community 37 - "Test Confluence"
Cohesion: 0.23
Nodes (5): ConfluenceApiService, UnsupportedDocumentTypeException, Illuminate\Http\Client\PendingRequest, Illuminate\Http\Client\Response, RuntimeException

### Community 39 - "Changeownpassword Clearstoreduser"
Cohesion: 0.06
Nodes (45): ad(), Ah(), bh(), cd(), dd(), ed(), emit(), fd() (+37 more)

### Community 40 - "Component Theme"
Cohesion: 0.09
Nodes (13): Theme, ThemeService, Injectable, AppShellComponent, Component, HeaderComponent, Component, Input (+5 more)

### Community 41 - "Test User"
Cohesion: 0.08
Nodes (5): ProjectAnalyticsController, ChatMessage, ChatMessageFeedback, Country, Illuminate\Database\Eloquent\Relations\HasMany

### Community 42 - "Attach Clone"
Cohesion: 0.13
Nodes (14): applyStyles(), applyToHost(), ch(), complete(), error(), jD(), ld(), next() (+6 more)

### Community 43 - "Component Page"
Cohesion: 0.14
Nodes (6): countryLabel(), Country, Tenant, TenantsPageComponent, Component, roles

### Community 44 - "Component Chatmessage"
Cohesion: 0.19
Nodes (3): ChatSession, ProjectChatPageComponent, Component

### Community 45 - "Attachview Compilemoduleandallcomponentsasync"
Cohesion: 0.12
Nodes (4): cA(), Jb(), Ji(), registerOnChange()

### Community 46 - "Construct Php"
Cohesion: 0.14
Nodes (18): Ar(), kr(), Li(), Lp(), Oi(), Pi(), Pr(), qc() (+10 more)

### Community 47 - "Angular Cli"
Cohesion: 0.09
Nodes (23): @angular/build, @angular/cli, @angular/compiler-cli, devDependencies, @angular/build, @angular/cli, @angular/compiler-cli, jsdom (+15 more)

### Community 48 - "Component Overview"
Cohesion: 0.16
Nodes (3): Project, ProjectsPageComponent, Component

### Community 49 - "Capture Consumeoptional"
Cohesion: 0.16
Nodes (4): getGlobalEventTarget(), onAndCancel(), runGuarded(), runOutsideAngular()

### Community 50 - "Angular Common"
Cohesion: 0.10
Nodes (21): @angular/common, @angular/compiler, @angular/elements, @angular/forms, @angular/platform-browser, @angular/router, @angular/ssr, dependencies (+13 more)

### Community 51 - "Vite Tailwindcss"
Cohesion: 0.10
Nodes (19): axios, concurrently, laravel-vite-plugin, devDependencies, axios, concurrently, laravel-vite-plugin, tailwindcss (+11 more)

### Community 52 - "Element Domain"
Cohesion: 0.14
Nodes (15): ApiItemResponse, ApiListResponse, ApiPaginatedResponse, ChatMessage, Citation, PhoneCall, PhoneCaller, ProjectAnalytics (+7 more)

### Community 53 - "Construct Contextretrievalservice"
Cohesion: 0.13
Nodes (6): WarmVoiceModelsCommand, ChatAnswerService, ContextRetrievalService, OllamaEmbeddingService, OllamaGenerationService, Illuminate\Console\Command

### Community 54 - "Server Routes"
Cohesion: 0.12
Nodes (11): App, appConfig, config, serverConfig, routes, serverRoutes, Component, AuthService (+3 more)

### Community 57 - "Createsnapshot Expandsegmentagainstrouteusingredirect"
Cohesion: 0.47
Nodes (6): eo(), jg(), qg(), retrieve(), sc(), tE()

### Community 59 - "Angular Shell"
Cohesion: 0.15
Nodes (17): Self Hosted Deploy Job, Project Conventions Guide, Graphify Query-First Rule, Confluence Ingestion Flow, RAG Queue Sync Mechanism, Angular CLI Workflow, Angular App Root Shell, Zetes Chat Embed Configuration (+9 more)

### Community 60 - "Php Authorizetelescopeaccess"
Cohesion: 0.16
Nodes (8): AuthorizeTelescopeAccess, ValidateTwilioRequest, ValidateWidgetRequest, TelescopeServiceProvider, Closure, Illuminate\Foundation\Configuration\Middleware, Laravel\Telescope\TelescopeApplicationServiceProvider, Symfony\Component\HttpFoundation\Response

### Community 62 - "Path Getpath"
Cohesion: 0.50
Nodes (3): b_(), encodeKey(), encodeValue()

### Community 65 - "Php Artisan"
Cohesion: 0.13
Nodes (15): scripts, post-autoload-dump, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall (+7 more)

### Community 66 - "Composer Json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

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
Cohesion: 0.15
Nodes (4): TwilioNumberService, TwilioNumberServiceTest, Twilio\Base\PhoneNumberCapabilities, Twilio\Rest\Client

### Community 77 - "Illuminate Projectchatmessagecreated"
Cohesion: 0.29
Nodes (5): ProjectChatMessageCreated, Illuminate\Broadcasting\InteractsWithSockets, Illuminate\Contracts\Broadcasting\ShouldBroadcastNow, Illuminate\Foundation\Events\Dispatchable, Illuminate\Queue\SerializesModels

### Community 78 - "Parse Supports"
Cohesion: 0.24
Nodes (3): ExcelParser, HtmlParser, DocumentParserInterface

### Community 79 - "Component Widgetsettings"
Cohesion: 0.33
Nodes (3): WidgetSettings, ProjectWidgetPageComponent, Component

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

### Community 108 - "Ngvalue Setelementvalue"
Cohesion: 0.13
Nodes (5): WidgetChatController, ChatSession, Carbon\CarbonInterface, Illuminate\Database\Eloquent\Relations\HasOne, ProjectChatTest

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
Cohesion: 0.06
Nodes (12): ProjectVoiceController, TwilioVoiceController, AnswerVoiceTurnJob, PhoneCall, AnswerOptions, VoiceResponseFormatter, VoiceSettings, VoiceTurnStore (+4 more)

### Community 162 - ".execute"
Cohesion: 0.16
Nodes (16): Bi(), complete(), el(), error(), eu(), hn(), $i(), next() (+8 more)

### Community 167 - "np"
Cohesion: 0.08
Nodes (35): Ag(), applyValueToInputSignal(), cb(), createSnapshot(), emit(), expandSegmentAgainstRouteUsingRedirect(), fb(), GE() (+27 more)

## Ambiguous Edges - Review These
- `Zetes Chat Embed Configuration` → `Angular Widget Deployment Shell`  [AMBIGUOUS]
  public/chat.html · relation: references

## Knowledge Gaps
- **191 isolated node(s):** `npx`, `@angular/cli`, `$schema`, `name`, `type` (+186 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **25 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `Zetes Chat Embed Configuration` and `Angular Widget Deployment Shell`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **Why does `t()` connect `Attach Createcomment` to `Rgdfdfoa Addasyncvalidators`, `Addasyncvalidators Addvalidators`, `Canceldelete Canceledit`, `Activatedcomponentref Activatedroute`, `Addclass Applyvaluetoinputsignal`, `Attachtoappref Createcomponentref`, `Get Appendchild`, `Applystyles Applytohost`, `Warn Addclass`, `Addcontrol Setvalue`, `Closepanel Feedbackfor`, `Destroy Adjustindex`, `Constructor Addheaderentry`, `Remove Unsubscribe`, `Complete Documentstatusentries`, `Applypaginationmeta Confirmdeletedocument`, `Chatdisplaytimestamp Createtext`, `Appendall Areallvisibleconfluencespacesselected`, `Constructor Assignasyncvalidators`, `Bootstrap Afterpreactivation`, `Getuseragent Producermustrecompute`, `Fac Withconfig`, `Core Group 35`, `.execute`, `np`, `Attach Clone`, `Component Chatmessage`, `Attachview Compilemoduleandallcomponentsasync`, `Createsnapshot Expandsegmentagainstrouteusingredirect`, `Path Getpath`?**
  _High betweenness centrality (0.139) - this node is a cross-community bridge._
- **Why does `ProjectChatPageComponent` connect `Component Chatmessage` to `Component Page`, `Element Domain`?**
  _High betweenness centrality (0.130) - this node is a cross-community bridge._
- **Why does `e` connect `Canceldelete Canceledit` to `Rgdfdfoa Addasyncvalidators`, `Addasyncvalidators Addvalidators`, `Activatedcomponentref Activatedroute`, `Addclass Applyvaluetoinputsignal`, `Attachtoappref Createcomponentref`, `Get Appendchild`, `Attach Createcomment`, `Applystyles Applytohost`, `Addcontrol Setvalue`, `Destroy Adjustindex`, `Remove Unsubscribe`, `Complete Documentstatusentries`, `Applypaginationmeta Confirmdeletedocument`, `Chatdisplaytimestamp Createtext`, `Appendall Areallvisibleconfluencespacesselected`, `Constructor Assignasyncvalidators`, `Bootstrap Afterpreactivation`, `Fac Withconfig`, `Core Group 35`, `Attach Clone`, `Component Chatmessage`, `Attachview Compilemoduleandallcomponentsasync`, `Capture Consumeoptional`, `Path Getpath`?**
  _High betweenness centrality (0.072) - this node is a cross-community bridge._
- **Are the 159 inferred relationships involving `t()` (e.g. with `_1()` and `Ah()`) actually correct?**
  _`t()` has 159 INFERRED edges - model-reasoned connections that need verification._
- **Are the 19 inferred relationships involving `u()` (e.g. with `t()` and `g()`) actually correct?**
  _`u()` has 19 INFERRED edges - model-reasoned connections that need verification._
- **What connects `npx`, `@angular/cli`, `$schema` to the rest of the system?**
  _191 weakly-connected nodes found - possible documentation gaps or missing edges._