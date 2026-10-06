# Attex reference study and AquaDissect mapping

Study date: October 6, 2026. All 86 requested destinations were opened in Chrome at desktop and 390 × 844 mobile sizes. This is an observation of a demonstration theme, not an endorsement or proof of backend functionality. Google/Vector maps were visible in the sidebar but excluded because they are outside the requested 86 pages.

## Evidence and limits

Each page has a retained JSON observation and desktop/mobile accessibility snapshot under `outputs/attex-study-2026-10-06/reference/`. Representative screenshots are linked below. Counts and headings come from the rendered DOM. Horizontal-overflow checks describe the settled viewport after dismissing the sidebar; they are not a complete responsive audit. Pointer hover, full screen-reader output, every disabled/loading state, drag persistence and backend integrations were not verified. Intended behavior is listed separately from tested actions. No reference forms, purchases, emails or chat messages were submitted.

The shared shell contains a hamburger control, sidebar groups, breadcrumb, search, language, notifications, application/configuration/theme/fullscreen controls, and an account identity. Group expansions exposed the full inventory. A retained open sidebar initially clipped mobile content; Escape closed it. That retained-state result is not evidence that every reference page is broken on mobile. AquaDissect keeps its own protected mobile drawer and account-scoped destinations.

## Pattern decisions

Figtree, compact headings, white solid cards on a pale blue-gray canvas, blue primary actions, readable status colors, consistent card padding, visible keyboard focus and subtle state transitions are retained/adapted. Reference screenshots include theme-specific colored demonstrations; AquaDissect uses no gradients. Commercial dashboards, chat, email delivery, pricing and invoices do not become new modules. Theme/language/fullscreen controls are not copied without a supported user task.

## Page checklist

### 01. Analytics

- Reference: [Analytics](https://themes.coderthemes.com/attex_mui/analytics)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Analytics; Enhance your Campaign for better outreach; Active Users; 825; Views Per Minute; 589; Sessions by Browser; Sessions by Operating System; 8,285; 3,534; Views Per Minute; Sessions Overview; 170K. 4 table surfaces; 7 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Today; 7d; 15d; 1m; 1y; Export; Export; Export.
- Verified observation: 7d accepted a click; Active Users 825 and Views Per Minute 589 remained unchanged. A filtered data query was not established.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Analytics; Enhance your Campaign for better outreach; Active Users; 825; Views Per Minute.
- AquaDissect mapping: **Dashboard**. Learning counts and actual report records; no marketing analytics.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/01-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/01-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/01.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/01-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/01-mobile.png)

### 02. Ecommerce

- Reference: [Ecommerce](https://themes.coderthemes.com/attex_mui/ecommerce)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Dashboard; Customers; 54,214; Orders; 7,543; Revenue; $9,254; Growth; + 20.6%; Conversation; + 9.62%; Revenue; $1705.54. 1 table surfaces; 8 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Export.
- Verified observation: Total Sales displayed “Something went wrong. Please refresh to get new data!”; example metrics are demonstration data.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Dashboard; Customers; 54,214; Orders; 7,543.
- AquaDissect mapping: **Dashboard**. Compact metric cards and empty/error feedback; revenue, orders and growth not applicable.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/02-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/02-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/02.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/02-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/02-mobile.png)

### 03. Calendar

- Reference: [Calendar](https://themes.coderthemes.com/attex_mui/apps/calendar)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Calender; How It Works ?; October 2026. 3 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Create New Event; New Theme Release; My Event; Meet manager; Create New theme; Prev; Next; Today; Month; Week; Day; List.
- Verified observation: Week became selected and changed the period heading; Create New Event opened a labelled dialog; Close dismissed it. No event was saved or moved.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Calender; How It Works ?; October 2026; About; Support.
- AquaDissect mapping: **Calendar**. Month/week/day/list, section filtering, event details and authorized date editing; drag movement deferred.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/03-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/03-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/03.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/03-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/03-mobile.png)

### 04. Chat

- Reference: [Chat](https://themes.coderthemes.com/attex_mui/apps/chat)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Chat; Attex-sketch.zip. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); People, groups & messages... (text); Send message quickly (text).
- Intended interactions / labels: English; 1; Voice Call; Video Call; Add Participant; Delete Chat.
- Verified observation: Selecting Brandon Smith changed the contact/header identity; transcript content retained template messages. No send/call/invite/delete action.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Chat; Attex-sketch.zip; About; Support; Contact Us.
- AquaDissect mapping: **Notifications**. Readable item selection and message spacing only; chat and delivery integration not available.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/04-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/04-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/04.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/04-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/04-mobile.png)

### 05. Inbox

- Reference: [Inbox](https://themes.coderthemes.com/attex_mui/apps/email/inbox)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Email Inbox; LABELS. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (checkbox).
- Intended interactions / labels: English; 1; Compose; More.
- Verified observation: Checkbox selection changed. Starred stayed on Inbox URL; folder filtering was not established.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Email Inbox; LABELS; About; Support; Contact Us.
- AquaDissect mapping: **Notifications**. Unread/read state and pagination; commercial mail folders and composing not applicable.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/05-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/05-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/05.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/05-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/05-mobile.png)

### 06. Read Email

- Reference: [Read Email](https://themes.coderthemes.com/attex_mui/apps/email/read)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Email Read; LABELS; Your elite author Graphic Optimization reward is ready!; Steven SmithJul 24, 2019, 5:17 AM; Attachments; Attex-admin-design.zip; Dashboard-design.jpg; Admin-bug-report.mp4. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Compose; More; Reply; Forward.
- Verified observation: Reading layout, attachments and Reply/Forward controls observed. No send, delete or attachment download.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Email Read; LABELS; Your elite author Graphic Optimization reward is ready!; Steven SmithJul 24, 2019, 5:17 AM; Attachments.
- AquaDissect mapping: **Notifications / content**. Readable detail and attachment provenance patterns; email send/forward not available.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/06-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/06-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/06.json)

### 07. List

- Reference: [List](https://themes.coderthemes.com/attex_mui/apps/tasks/list)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Task List; Today(3); Upcoming(3); Other(3); Arya Stark; Today 10am; This is a simple editable area.; Arya Stark; Gary Somya. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Search Files... (text); task-1 (checkbox); task-2 (checkbox); task-3 (checkbox); task-4 (checkbox); task-5 (checkbox); task-6 (checkbox); task-7 (checkbox); task-8 (checkbox); task-9 (checkbox); Unlabelled reference field (checkbox); Unlabelled reference field (text); checklist-1 (checkbox).
- Intended interactions / labels: English; 1; Submit.
- Verified observation: Typing iOS in Search Files left all nine task checkboxes visible after settling; observed search did not filter.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Task List; Today(3); Upcoming(3); Other(3); Arya Stark.
- AquaDissect mapping: **Class activities**. Searchable assigned activity tables and explicit statuses; task comments not available.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/07-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/07-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/07.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/07-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/07-mobile.png)

### 08. Details

- Reference: [Details](https://themes.coderthemes.com/attex_mui/apps/tasks/details)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Project Detail; Draft the new contract document for sales team; Arya Stark; Examron Envirenment; Today 10am; Overview:; Checklists/Sub-tasks; Comments (51); Jeremy Tomlinson; Thelma Fridley; Kevin Martinez; Attachments; Drop files here or click to upload.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (checkbox); checklist-1 (checkbox); checklist-2 (checkbox); checklist-3 (checkbox); Your Comment... (textarea); Unlabelled reference field (textarea); Unlabelled reference field (file).
- Intended interactions / labels: English; 1; Reply; Reply; Reply; Load More; Submit.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Project Detail; Draft the new contract document for sales team; Arya Stark; Examron Envirenment; Today 10am.
- AquaDissect mapping: **Safety / tutorials**. Numbered steps and completion states; task comments and generic project tasks not applicable.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/08-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/08-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/08.json)

### 09. Kanban Board

- Reference: [Kanban Board](https://themes.coderthemes.com/attex_mui/apps/kanban)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Kanban; Todo (3); iOS App home page; Topnav layout design; Invite user to a project; In Progress (2); Write a release note; Enable analytics tracking; Review (4); Kanban board design; Code HTML email template; Brand logo design; Improve animation loader. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Add Section.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Kanban; Todo (3); iOS App home page; Topnav layout design; Invite user to a project.
- AquaDissect mapping: **Content review**. Draft/review/published lifecycle as existing records; draggable Kanban not added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/09-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/09-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/09.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/09-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/09-mobile.png)

### 10. File Manager

- Reference: [File Manager](https://themes.coderthemes.com/attex_mui/apps/file-manager)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: File Manager; Storage; Quick Access; Recent. 1 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); People, groups & messages... (text).
- Intended interactions / labels: English; 1; Create New.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: File Manager; Storage; Quick Access; Recent; About.
- AquaDissect mapping: **Content / offline / source**. Scoped file lists, asset details and download feedback; cloud drives and arbitrary file management not available.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/10-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/10-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/10.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/10-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/10-mobile.png)

### 11. Starter Page

- Reference: [Starter Page](https://themes.coderthemes.com/attex_mui/pages/starter)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Starter. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Starter; About; Support; Contact Us.
- AquaDissect mapping: **All portal pages**. Consistent heading, breadcrumb and content container.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/11-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/11-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/11.json)

### 12. Profile

- Reference: [Profile](https://themes.coderthemes.com/attex_mui/pages/profile)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Profile; About Me :; Messages; Projects; Experience; Lead designer / Developer; Senior Graphic Designer; Graphic Designer. 1 table surfaces; 1 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Follow; Message; Reply; Reply; Reply; Reply; Reply; About; Timeline; Settings.
- Verified observation: Settings became the selected tab and displayed account/company/social form fields; no submission.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Profile; About Me :; Messages; Projects; Experience.
- AquaDissect mapping: **Account menu / role guide**. Account context and role-specific navigation; social profile editing not available.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/12-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/12-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/12.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/12-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/12-mobile.png)

### 13. Timeline

- Reference: [Timeline](https://themes.coderthemes.com/attex_mui/pages/timeline)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Timeline; Today; Completed UX design project for our client; Yay! We are celebrating our first admin release.; Yesterday; We released new version of our theme Attex.; We have archieved 25k sales in our themes.; Conference call with UX team; Last Month; Join new team member Alex Smith; Alex Smith - Senior Software (Full Stack); First release of Attex admin dashboard template. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; 👍17; ❤️89; 🎉148; 🏆94; 👍1.4k; 🎉2k; ❤️89; 🎉10k; 👍3.2k; ❤️7.1k.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Timeline; Today; Completed UX design project for our client; Yay! We are celebrating our first admin release.; Yesterday.
- AquaDissect mapping: **Source / review history**. Existing audit and source timeline patterns; no fabricated events.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/13-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/13-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/13.json)

### 14. Invoice

- Reference: [Invoice](https://themes.coderthemes.com/attex_mui/pages/invoice)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Invoice; Order Date:; Order Status:; Order ID:; Billing Address; Shipping Address; Sub-total:; VAT (12.5):; $4635.00 USD. 1 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Print; Submit.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Invoice; Order Date:; Order Status:; Order ID:; Billing Address.
- AquaDissect mapping: **Not applicable**. No billing or invoice workflow.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/14-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/14-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/14.json)

### 15. FAQ

- Reference: [FAQ](https://themes.coderthemes.com/attex_mui/pages/faq)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: FAQs; Frequently Asked Questions; What does LOREM mean?; Where can I subscribe to your newsletter?; Where can I edit my billing and shipping address?; Can I order a free sample copy of a magazine?; Are unsold magazines sent back to the publisher?; When can be used?; License & Copyright; Do I have to pay customs or import fees?. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Email us your question; Send us a tweet.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: FAQs; Frequently Asked Questions; What does LOREM mean?; Where can I subscribe to your newsletter?; Where can I edit my billing and shipping address?.
- AquaDissect mapping: **Help & guide**. Searchable plain-language tutorials and prerequisite guidance.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/15-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/15-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/15.json)

### 16. Pricing

- Reference: [Pricing](https://themes.coderthemes.com/attex_mui/pages/pricing)
- Layout: Shared workspace shell and task-specific cards/panes.
- Components observed: Pricing; OurPlans; Professional Pack; $19/ Month; Professional Pack; $29/ Month; Professional Pack; $39/ Month. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Change Plan; Change Plan; Change Plan.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Pricing; OurPlans; Professional Pack; $19/ Month; Professional Pack.
- AquaDissect mapping: **Not applicable**. No paid plans or checkout.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/16-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/16-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/16.json)

### 17. Maintenance

- Reference: [Maintenance](https://themes.coderthemes.com/attex_mui/maintenance)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Site is Under Maintenance; Why is the Site Down?; Why is the Site Down?; Why is the Site Down?. 0 table surfaces; 0 chart surfaces.
- Form fields observed: No visible form fields recorded.
- Intended interactions / labels: Read content and follow visible links; no interaction implied for decorative content..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Site is Under Maintenance; Why is the Site Down?; Why is the Site Down?; Why is the Site Down?.
- AquaDissect mapping: **Error / offline**. Clear unavailable-state language and recovery destination; no maintenance scheduling added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/17-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/17-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/17.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/17-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/17-mobile.png)

### 18. Login

- Reference: [Login](https://themes.coderthemes.com/attex_mui/auth/login)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Login In. 0 table surfaces; 0 chart surfaces.
- Form fields observed: email (email); password (password); rememberMe (checkbox).
- Intended interactions / labels: Login.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Login In.
- AquaDissect mapping: **Login**. Compact form, Google sign-in, validation and pending state using existing auth.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/18-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/18-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/18.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/18-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/18-mobile.png)

### 19. Login 2

- Reference: [Login 2](https://themes.coderthemes.com/attex_mui/auth/login2)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Log In; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work.; Feature Availability!; This is a great product, helped us a lot and very quick to work with and implement.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: email (email); password (password); rememberMe (checkbox).
- Intended interactions / labels: Login.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Log In; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work..
- AquaDissect mapping: **Login**. Alternative illustration panel is not needed; retain compact mobile-first entry.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/19-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/19-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/19.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/19-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/19-mobile.png)

### 20. Register

- Reference: [Register](https://themes.coderthemes.com/attex_mui/auth/register)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Free Register. 0 table surfaces; 0 chart surfaces.
- Form fields observed: fullName (text); email (email); password (password); rememberMe (checkbox).
- Intended interactions / labels: Login.
- Verified observation: Free Register form displayed Full Name/Email/Password, but submit button label was Login. No registration submitted.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Free Register.
- AquaDissect mapping: **Instructor registration**. Existing approval request with accurate action labels; no public Program Head/admin signup.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/20-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/20-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/20.json)

### 21. Register 2

- Reference: [Register 2](https://themes.coderthemes.com/attex_mui/auth/register2)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Free Register; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work.; Feature Availability!; This is a great product, helped us a lot and very quick to work with and implement.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: fullName (text); email (email); password (password); rememberMe (checkbox).
- Intended interactions / labels: Login.
- Verified observation: Alternative registration layout observed; successful registration and backend validation unverified.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Free Register; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work..
- AquaDissect mapping: **Instructor registration**. Same request flow, no duplicate registration backend.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/21-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/21-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/21.json)

### 22. Logout

- Reference: [Logout](https://themes.coderthemes.com/attex_mui/auth/logout)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: See You Again !. 0 table surfaces; 0 chart surfaces.
- Form fields observed: No visible form fields recorded.
- Intended interactions / labels: Read content and follow visible links; no interaction implied for decorative content..
- Verified observation: Logout example navigated to a login destination during inspection; backend session invalidation was not independently established.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: See You Again !.
- AquaDissect mapping: **Sign out**. Existing server logout and sign-in destination; no sample session logic copied.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/22-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/22-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/22.json)

### 23. Logout 2

- Reference: [Logout 2](https://themes.coderthemes.com/attex_mui/auth/logout2)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: See You Again !; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work.; Feature Availability!; This is a great product, helped us a lot and very quick to work with and implement.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: No visible form fields recorded.
- Intended interactions / labels: Read content and follow visible links; no interaction implied for decorative content..
- Verified observation: Logout illustration observed after restoring the example session route; no AquaDissect session was changed.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: See You Again !; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work..
- AquaDissect mapping: **Sign out**. Same existing logout route.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/23-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/23-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/23.json)

### 24. Recover Password

- Reference: [Recover Password](https://themes.coderthemes.com/attex_mui/auth/recover-password)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Reset Password. 0 table surfaces; 0 chart surfaces.
- Form fields observed: email (email).
- Intended interactions / labels: Reset Password.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Reset Password.
- AquaDissect mapping: **Help / activation**. Recovery guidance only; no password email service invented.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/24-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/24-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/24.json)

### 25. Recover Password 2

- Reference: [Recover Password 2](https://themes.coderthemes.com/attex_mui/auth/recover-password2)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Reset Password; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work.; Feature Availability!; This is a great product, helped us a lot and very quick to work with and implement.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: email (email).
- Intended interactions / labels: Reset Password.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Reset Password; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work..
- AquaDissect mapping: **Help / activation**. Same recovery limitation.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/25-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/25-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/25.json)

### 26. Lock Screen

- Reference: [Lock Screen](https://themes.coderthemes.com/attex_mui/auth/lock-screen)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Hi! Tosha. 0 table surfaces; 0 chart surfaces.
- Form fields observed: password (password).
- Intended interactions / labels: Login.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Hi! Tosha.
- AquaDissect mapping: **Session expiry**. Existing authentication redirect; lock-screen unlock not available.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/26-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/26-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/26.json)

### 27. Lock Screen 2

- Reference: [Lock Screen 2](https://themes.coderthemes.com/attex_mui/auth/lock-screen2)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Hi! Tosha; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work.; Feature Availability!; This is a great product, helped us a lot and very quick to work with and implement.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: password (password).
- Intended interactions / labels: Login.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Hi! Tosha; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work..
- AquaDissect mapping: **Session expiry**. Same server-authenticated redirect, no fake local unlock.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/27-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/27-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/27.json)

### 28. Confirm Mail

- Reference: [Confirm Mail](https://themes.coderthemes.com/attex_mui/auth/confirm-mail)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Please check your email. 0 table surfaces; 0 chart surfaces.
- Form fields observed: No visible form fields recorded.
- Intended interactions / labels: Back to Home.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Please check your email.
- AquaDissect mapping: **Activation**. Existing invitation/Google activation states; no confirmation email delivery claim.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/28-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/28-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/28.json)

### 29. Confirm Mail 2

- Reference: [Confirm Mail 2](https://themes.coderthemes.com/attex_mui/auth/confirm-mail2)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Please check your email; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work.; Feature Availability!; This is a great product, helped us a lot and very quick to work with and implement.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: No visible form fields recorded.
- Intended interactions / labels: Back to Home.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Please check your email; I love the color!; Everything you need is in this template. Love the overall look and feel. Not too flashy, and still very professional and smart.; Flexibility !; Pretty nice theme, hoping you guys could add more features to this. Keep up the good work..
- AquaDissect mapping: **Activation**. Same existing flow.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/29-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/29-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/29.json)

### 30. Error 404

- Reference: [Error 404](https://themes.coderthemes.com/attex_mui/error-404)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: 44; Page Not Found. 0 table surfaces; 0 chart surfaces.
- Form fields observed: No visible form fields recorded.
- Intended interactions / labels: Back to Home.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: 44; Page Not Found.
- AquaDissect mapping: **Error states**. Shared compact Figtree not-found guidance and safe account destination.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/30-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/30-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/30.json)

### 31. Error 404-alt

- Reference: [Error 404-alt](https://themes.coderthemes.com/attex_mui/error-404-alt)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: Error 404; 404; Page Not Found. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Return Home.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; document horizontal overflow detected; adaptation must contain it. Mobile visible headings: Error 404; 404; Page Not Found; About; Support.
- AquaDissect mapping: **Error states**. Avoid observed alternative-page overflow; reuse the same compact not-found layout.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/31-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/31-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/31.json)

### 32. Error 500

- Reference: [Error 500](https://themes.coderthemes.com/attex_mui/error-500)
- Layout: Standalone authentication/service-state layout; shell may be absent.
- Components observed: 500; INTERNAL SERVER ERROR. 0 table surfaces; 0 chart surfaces.
- Form fields observed: No visible form fields recorded.
- Intended interactions / labels: Return Home.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: 500; INTERNAL SERVER ERROR.
- AquaDissect mapping: **Error states**. Shared compact server-error guidance without diagnostic details.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/32-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/32-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/32.json)

### 33. Accordions

- Reference: [Accordions](https://themes.coderthemes.com/attex_mui/ui/accordions)
- Layout: Component/example cards inside the shared shell.
- Components observed: Accordions; Default; Disabled; Customized; Custom Icon. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Accordions; Default; Disabled; Customized; Custom Icon.
- AquaDissect mapping: **Help / safety**. Existing disclosure steps; native details for table columns and navigation groups.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/33-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/33-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/33.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/33-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/33-mobile.png)

### 34. Alerts

- Reference: [Alerts](https://themes.coderthemes.com/attex_mui/ui/alerts)
- Layout: Component/example cards inside the shared shell.
- Components observed: Alerts; Basic; Custom Icons; Descriptive; Variants Snackbar; Actions on Alerts; Simple Snackbar; Outlined Variant; Filled Variant; Controlled Positioning; Controlled Slide Direction; Transitioned; Transition Variant. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Open error snackbar; Open warning snackbar; Open info snackbar; Open success snackbar; Close; UNDO; Open simple snackbar; Top-Center; Top-Left; Top-Right; Bottom-Left; Bottom-Right; Bottom-Center; Up; Left; Right.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Alerts; Basic; Custom Icons; Descriptive; Variants Snackbar.
- AquaDissect mapping: **Shared feedback**. Status announcements, retry and persistent error messages.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/34-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/34-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/34.json)

### 35. Avatars

- Reference: [Avatars](https://themes.coderthemes.com/attex_mui/ui/avatars)
- Layout: Component/example cards inside the shared shell.
- Components observed: Avatars; Image Avatars; Grouped Avatars; Letter Avatars; Icon Avatars; Sizes Avatars; Variant Avatars; Avatars With badge. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Avatars; Image Avatars; Grouped Avatars; Letter Avatars; Icon Avatars.
- AquaDissect mapping: **Account / roster**. Existing initials; no personal photo collection added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/35-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/35-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/35.json)

### 36. Buttons

- Reference: [Buttons](https://themes.coderthemes.com/attex_mui/ui/buttons)
- Layout: Component/example cards inside the shared shell.
- Components observed: Buttons; Contained; Outlined; Text; Elevation Disabled; Size Variants; Buttons with icons and label; Icon; Colored Icon; Grouped Button Variants; Grouped Buttons with Sizes and colors; Vertical Grouped; Block Size. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Primary; Secondary; Success; Error; Warning; Info; Inherit; Primary; Secondary; Success; Error; Warning; Info; Inherit; Primary; Secondary.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Buttons; Contained; Outlined; Text; Elevation Disabled.
- AquaDissect mapping: **All controls**. Primary/secondary/disabled/focus states and 44px touch controls.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/36-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/36-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/36.json)

### 37. Badges

- Reference: [Badges](https://themes.coderthemes.com/attex_mui/ui/badges)
- Layout: Component/example cards inside the shared shell.
- Components observed: Badges; Default; Text; Attex; Attex; Attex; Attex; Attex; Attex; Outlined Chips; Filled Chips; Adornments Chips; Alignment. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Inbox; Notification; Likes; Total Views; Messages; Today's Revenue.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Badges; Default; Text; Attex; Attex.
- AquaDissect mapping: **Status / unread**. Existing explicit status labels and notification count, not color alone.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/37-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/37-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/37.json)

### 38. Breadcrumb

- Reference: [Breadcrumb](https://themes.coderthemes.com/attex_mui/ui/breadcrumbs)
- Layout: Component/example cards inside the shared shell.
- Components observed: Breadcrumbs; Simple Breadcrumb; Custom Separator; Breadcrumb with Icon; Collapsed Breadcrumb. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Show path.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Breadcrumbs; Simple Breadcrumb; Custom Separator; Breadcrumb with Icon; Collapsed Breadcrumb.
- AquaDissect mapping: **Navigation**. Semantic breadcrumb and current-page label.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/38-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/38-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/38.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/38-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/38-mobile.png)

### 39. Cards

- Reference: [Cards](https://themes.coderthemes.com/attex_mui/ui/cards)
- Layout: Component/example cards inside the shared shell.
- Components observed: Cards; Vanilla Juice; Promotion Party 🎉; Finding Nemo 🐠; Card title; Card title; Card title; Card title; Card title; Card title; 23.1K; 652. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Share; Celebrate Together; Share; Do Something; Do Something; Do Something; Share; Learn More; previous; play/pause; next; settings; add to favorites; share; Edit Profile.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Cards; Vanilla Juice; Promotion Party 🎉; Finding Nemo 🐠; Card title.
- AquaDissect mapping: **Dashboard / learning**. Consistent compact card borders, heading spacing and solid surfaces.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/39-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/39-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/39.json)

### 40. Collapse

- Reference: [Collapse](https://themes.coderthemes.com/attex_mui/ui/collapses)
- Layout: Component/example cards inside the shared shell.
- Components observed: Collapses; Read More; List Items; Simple; Fade Animation; Grow Animation; Slide Animation. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (checkbox).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Collapses; Read More; List Items; Simple; Fade Animation.
- AquaDissect mapping: **Navigation / table detail**. Actual disclosure state and aria-expanded.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/40-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/40-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/40.json)

### 41. Dismissible

- Reference: [Dismissible](https://themes.coderthemes.com/attex_mui/ui/dismissibles)
- Layout: Component/example cards inside the shared shell.
- Components observed: Dismissible; Alerts; Animated. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Re-open; Close; UNDO; Re-open; close.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Dismissible; Alerts; Animated; About; Support.
- AquaDissect mapping: **Feedback**. Existing dismissible toast; no dismissal of essential validation messages.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/41-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/41-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/41.json)

### 42. Menus

- Reference: [Menus](https://themes.coderthemes.com/attex_mui/ui/menus)
- Layout: Component/example cards inside the shared shell.
- Components observed: Menus; Default; Icon; Dense; Selected Options; Positioned; Account; Custom Designed Menu; Max Height; Fade Transition; Color Variants; Positioned; Context. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Default Menu; Icon Menu; Dense Menu; Dashboard; M; Options; more; Dashboard; Primary; Secondary; Success; Error; Warning; Info; Center Bottom; Center Center.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Menus; Default; Icon; Dense; Selected Options.
- AquaDissect mapping: **Account / tables**. Existing Bootstrap account menu and optional columns disclosure.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/42-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/42-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/42.json)

### 43. Progress

- Reference: [Progress](https://themes.coderthemes.com/attex_mui/ui/progresses)
- Layout: Component/example cards inside the shared shell.
- Components observed: Progress; Default Linear; Linear Determinate; Linear Buffer; Linear Determinate; Color Variant Linear. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Progress; Default Linear; Linear Determinate; Linear Buffer; Linear Determinate.
- AquaDissect mapping: **Safety / lessons**. Existing recorded progress; no fake completion percentages.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/43-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/43-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/43.json)

### 44. Skeleton

- Reference: [Skeleton](https://themes.coderthemes.com/attex_mui/ui/skeletons)
- Layout: Component/example cards inside the shared shell.
- Components observed: Skeleton; Skeleton Variants; Colored Skeleton; Skeleton Animation; Inferring Dimensions; Pulsating Animation; Wave Animation. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; settings.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; document horizontal overflow detected; adaptation must contain it. Mobile visible headings: Skeleton; Skeleton Variants; Colored Skeleton; Skeleton Animation; Inferring Dimensions.
- AquaDissect mapping: **Workspace loading**. Non-gradient skeleton with status text and reduced-motion fallback.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/44-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/44-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/44.json)

### 45. Spinners

- Reference: [Spinners](https://themes.coderthemes.com/attex_mui/ui/spinners)
- Layout: Component/example cards inside the shared shell.
- Components observed: Spinners; Default Circular; Colored Circular; Circular Determinate; Circular With Label; Delayed Appearance; Interactive Integration. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Loading; Simulate a load; save; Accept terms.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Spinners; Default Circular; Colored Circular; Circular Determinate; Circular With Label.
- AquaDissect mapping: **Uploads / requests**. Existing pending controls and workspace status; no endless spinner replacing errors.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/45-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/45-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/45.json)

### 46. Lists

- Reference: [Lists](https://themes.coderthemes.com/attex_mui/ui/lists)
- Layout: Component/example cards inside the shared shell.
- Components observed: Lists; Basic; Nested List; Folders; Active Item; Primary Action Checkbox; Secondary Action Checkbox; Customized Example; Aligned Avatar; Interactive; Secondary Action Switch; Sticky Subheader. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (checkbox).
- Intended interactions / labels: English; 1; Project Settings; delete; delete; delete.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Lists; Basic; Nested List; Folders; Active Item.
- AquaDissect mapping: **Notifications / guides**. Readable lists and clear selected/unread state.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/46-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/46-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/46.json)

### 47. Ratio

- Reference: [Ratio](https://themes.coderthemes.com/attex_mui/ui/ratios)
- Layout: Component/example cards inside the shared shell.
- Components observed: Ratio; Aspect Ratio 21:9; Aspect Ratio 16:9; Aspect Ratio 1:1; Aspect Ratio 4:3. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Ratio; Aspect Ratio 21:9; Aspect Ratio 16:9; Aspect Ratio 1:1; Aspect Ratio 4:3.
- AquaDissect mapping: **Media**. Existing responsive aspect-ratio media, no new remote content.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/47-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/47-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/47.json)

### 48. Tabs

- Reference: [Tabs](https://themes.coderthemes.com/attex_mui/ui/tabs)
- Layout: Component/example cards inside the shared shell.
- Components observed: Tabs; Basic; Wrapped Labels; Colored; Disabled; Centered; Scrollable and Automatic scroll buttons; Forced scroll buttons; Prevent scroll buttons; Icon; Icon with Labels; Icon position; Full Width. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Item One; Item Two; Item Three; New Arrivals in the Longest Text of Nonfiction that should appear in the next line; Item Two; Item Three; Item One; Item Two; Item Three; Item One; Item Two; Item Three; Active; Disabled; Active; Item One.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Tabs; Basic; Wrapped Labels; Colored; Disabled.
- AquaDissect mapping: **Calendar / tutorials**. Selected calendar views and existing guide tabs; no inaccessible generic tab imitation.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/48-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/48-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/48.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/48-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/48-mobile.png)

### 49. Dialogs

- Reference: [Dialogs](https://themes.coderthemes.com/attex_mui/ui/dialogs)
- Layout: Component/example cards inside the shared shell.
- Components observed: Dialogs; Alert; Transition; Form; Customized; Full Screen; Max Width; Responsive Full Screen; Draggable; Basic; Scrollable; Confirmation; Use Google's location service?. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (radio).
- Intended interactions / labels: English; 1; Open alert dialog; Slide in alert dialog; Open form dialog; Open dialog; Open full-screen dialog; Open max-width dialog; Open responsive dialog; Open draggable dialog; Open simple dialog; scroll=paper; scroll=body; Disagree; Agree; Cancel; Ok.
- Verified observation: Open alert dialog displayed a labelled location-service example and focused Agree. Escape closed it after its exit transition. No agreement accepted.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Dialogs; Alert; Transition; Form; Customized.
- AquaDissect mapping: **Confirm / calendar**. Native dialog, keyboard Escape, focus restoration and event backdrop handling.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/49-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/49-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/49.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/49-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/49-mobile.png)

### 50. Drawer

- Reference: [Drawer](https://themes.coderthemes.com/attex_mui/ui/drawers)
- Layout: Component/example cards inside the shared shell.
- Components observed: Drawer; Temporary; Swipable. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Left; Right; Top; Bottom; Left; Right; Top; Bottom.
- Verified observation: Temporary left drawer opened Inbox/Starred/Send email/Drafts; Escape closed it.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Drawer; Temporary; Swipable; About; Support.
- AquaDissect mapping: **Mobile navigation**. Existing off-canvas focus trap, scrim, Escape and focus return.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/50-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/50-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/50.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/50-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/50-mobile.png)

### 51. Popovers

- Reference: [Popovers](https://themes.coderthemes.com/attex_mui/ui/popovers)
- Layout: Component/example cards inside the shared shell.
- Components observed: Popover; Basic; Hoverable; Virtual element; Anchor. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (radio); position-top (number); position-left (number).
- Intended interactions / labels: English; 1; Open Popover; Open Popover.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Popover; Basic; Hoverable; Virtual element; Anchor.
- AquaDissect mapping: **Search / table columns**. Anchored results and disclosure; no hover-only essential action.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/51-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/51-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/51.json)

### 52. Tooltips

- Reference: [Tooltips](https://themes.coderthemes.com/attex_mui/ui/tooltips)
- Layout: Component/example cards inside the shared shell.
- Components observed: Tooltips; Basic; Customized; Arrow; Triggers; Controlled; Custom Width; Transitioned; Follow Cursor; Virtual Element; Showing and Hiding Delay; Positioned. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; Read Mails; More Info; Unread Messages; Delete; Light; Bootstrap; HTML; Arrow; Hover or touch; Focus or touch; Hover; Click; Controlled; Default Width [300px]; Custom Width [500px]; No wrapping.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Tooltips; Basic; Customized; Arrow; Triggers.
- AquaDissect mapping: **Icon controls**. Accessible names and titles, not tooltip-only instructions.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/52-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/52-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/52.json)

### 53. Typography

- Reference: [Typography](https://themes.coderthemes.com/attex_mui/ui/typographies)
- Layout: Component/example cards inside the shared shell.
- Components observed: Typography; Component; h1. Heading; h2. Heading; h3. Heading; h4. Heading; h5. Heading; h6. Heading; subtitle1. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Quos blanditiis tenetur; subtitle2. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Quos blanditiis tenetur; Colored; The quick brown fox jumps over the lazy dog.; The quick brown fox jumps over the lazy dog.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Typography; Component; h1. Heading; h2. Heading; h3. Heading.
- AquaDissect mapping: **All pages**. Existing Figtree scale: body 14px, page 20px, section 18px; compact hierarchy.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/53-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/53-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/53.json)

### 54. Swiper

- Reference: [Swiper](https://themes.coderthemes.com/attex_mui/extended-ui/swipers)
- Layout: Component/example cards inside the shared shell.
- Components observed: Swiper; Default; Navigation & Pagination; Pagination Dynamic; Pagination Fraction; Pagination Custom; Pagination Progress; Scrollbar; Vertical; Mouse Wheel Control; Effect Fade; Effect Creative; Effect Flip. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; document horizontal overflow detected; adaptation must contain it. Mobile visible headings: Swiper; Default; Navigation & Pagination; Pagination Dynamic; Pagination Fraction.
- AquaDissect mapping: **Not applicable**. Learning steps require explicit navigation; no autoplay promotional carousel.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/54-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/54-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/54.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/54-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/54-mobile.png)

### 55. Nestable List

- Reference: [Nestable List](https://themes.coderthemes.com/attex_mui/extended-ui/nestable-list)
- Layout: Component/example cards inside the shared shell.
- Components observed: Nestable List; Simple DragNDrop; Card title; Card title; Card title; Card title; Card title; Card title; Move Between Different Containers; Container 1; Container 2; Move stuff between containers using handle; Container 1. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1; settings; settings; settings; settings; settings; settings.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Nestable List; Simple DragNDrop; Card title; Card title; Card title.
- AquaDissect mapping: **Source Structure**. Existing tree navigation; source read-only, no reordering.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/55-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/55-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/55.json)

### 56. Ratings

- Reference: [Ratings](https://themes.coderthemes.com/attex_mui/extended-ui/ratings)
- Layout: Component/example cards inside the shared shell.
- Components observed: Ratings; Basic; Precision; Sizes; Customized; Feedback on hover; Radio group. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); :r1: (radio); :r2: (radio); :r3: (radio); :r4: (radio); :r5: (radio); simple-controlled-empty (radio); :rd: (radio); :re: (radio); :rf: (radio); :rg: (radio); :rh: (radio); :rj: (radio); :rk: (radio).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Ratings; Basic; Precision; Sizes; Customized.
- AquaDissect mapping: **Not applicable**. Formative activity scores are recorded results, not star ratings or validation.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/56-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/56-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/56.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/56-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/56-mobile.png)

### 57. Player

- Reference: [Player](https://themes.coderthemes.com/attex_mui/extended-ui/players)
- Layout: Component/example cards inside the shared shell.
- Components observed: Player; Youtube; Vimeo. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Seek (range); Volume (range).
- Intended interactions / labels: English; 1; PausePlay; UnmuteMute; Settings; Exit fullscreenEnter fullscreen; Play.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Player; Youtube; Vimeo; About; Support.
- AquaDissect mapping: **Lesson / safety media**. Existing play/pause/replay/captions and reduced motion; no third-party player integrations.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/57-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/57-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/57.json)

### 58. Scrollbar

- Reference: [Scrollbar](https://themes.coderthemes.com/attex_mui/extended-ui/scrollbars)
- Layout: Component/example cards inside the shared shell.
- Components observed: Scrollbar; Default; Design it as you want; Lightweight and performant; Supported everywhere; RTL; Design it as you want; Lightweight and performant; Supported everywhere; Large; Design it as you want; Lightweight and performant; Supported everywhere. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Scrollbar; Default; Design it as you want; Lightweight and performant; Supported everywhere.
- AquaDissect mapping: **Tables / sidebar**. Contained horizontal table and vertical sidebar scrolling.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/58-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/58-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/58.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/58-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/58-mobile.png)

### 59. Lucide icons

- Reference: [Lucide icons](https://themes.coderthemes.com/attex_mui/icons/lucide-icons)
- Layout: Component/example cards inside the shared shell.
- Components observed: Lucide Icons; All Icons. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Lucide Icons; All Icons; About; Support; Contact Us.
- AquaDissect mapping: **All pages**. Existing locally bundled Lucide icons with decorative/accessible roles.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/59-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/59-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/59.json)

### 60. Basic Elements

- Reference: [Basic Elements](https://themes.coderthemes.com/attex_mui/forms/basic-elements)
- Layout: Component/example cards inside the shared shell.
- Components observed: Basic Elements; Basic TextField; Icons; Full Width; Validation; Form Props; Adornment; Select; Colored; Margin; Sizes; Multiline. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); :r0: (text); :r1: (text); :r2: (text); input-with-icon-adornment (text); input-with-icon-textfield (text); input-with-sx (text); full-width (text); outlined-error (text); filled-error (text); standard-error (text); outlined-error-helper-text (text); filled-error-helper-text (text); standard-error-helper-text (text).
- Intended interactions / labels: English; 1; toggle password visibility; toggle password visibility; toggle password visibility.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Basic Elements; Basic TextField; Icons; Full Width; Validation.
- AquaDissect mapping: **Staff / login forms**. Existing labels, constraints and errors; preserve server validation.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/60-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/60-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/60.json)

### 61. Editors

- Reference: [Editors](https://themes.coderthemes.com/attex_mui/forms/editors)
- Layout: Component/example cards inside the shared shell.
- Components observed: Editors; Snow Editor; Hello World!; This is an simple editable area.; Bubble Editor; Hello World!; This is an simple editable area.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (select-one); Unlabelled reference field (text).
- Intended interactions / labels: English; 1.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Editors; Snow Editor; Hello World!; This is an simple editable area.; Bubble Editor.
- AquaDissect mapping: **Content**. Existing structured content forms; arbitrary rich-text HTML editor not added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/61-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/61-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/61.json)

### 62. File Uploads

- Reference: [File Uploads](https://themes.coderthemes.com/attex_mui/forms/file-uploads)
- Layout: Component/example cards inside the shared shell.
- Components observed: File Upload; Dropzone; Drop files here or click to upload.. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Unlabelled reference field (file).
- Intended interactions / labels: English; 1; Send Files.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: File Upload; Dropzone; Drop files here or click to upload.; About; Support.
- AquaDissect mapping: **Media drafts**. Existing provenance/licence/size/hash review workflow; no arbitrary upload dropzone.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/62-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/62-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/62.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/62-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/62-mobile.png)

### 63. Form Layout

- Reference: [Form Layout](https://themes.coderthemes.com/attex_mui/forms/layout)
- Layout: Component/example cards inside the shared shell.
- Components observed: Layout; Basic Example; Horizontal form; Basic Example; Grid. 0 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Enter email (email); Enter password (password); rememberMe (checkbox); Email (email); Password (password); email@example.com (text); Jane Doe (text); Username (text); 1234 main st (text); Apartment, studio, or floor (text); Unlabelled reference field (text); zip (text).
- Intended interactions / labels: English; 1; Submit; Submit; Confirm identity; Submit; Sign in.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Layout; Basic Example; Horizontal form; Basic Example; Grid.
- AquaDissect mapping: **Report filters / staff forms**. Compact aligned labels and responsive stacking.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/63-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/63-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/63.json)

### 64. Basic Tables

- Reference: [Basic Tables](https://themes.coderthemes.com/attex_mui/tables/basic-tables)
- Layout: Component/example cards inside the shared shell.
- Components observed: Basic Tables; Simple; Enhanced; Customized; Custom Pagination; Sticky Header; Column Grouping; Collapsible Rows; Spanning. 8 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); select all desserts (checkbox); Unlabelled reference field (checkbox); Unlabelled reference field (text); rows per page (select-one).
- Intended interactions / labels: English; 1; Filter list; Go to previous page; Go to next page; first page; previous page; next page; last page; Go to previous page; Go to next page; Go to previous page; Go to next page; expand row; expand row; expand row; expand row; expand row.
- Verified observation: Calories sort changed ascending to descending. Next changed pagination to rows 6–10 of 13.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Basic Tables; Simple; Enhanced; Customized; Custom Pagination.
- AquaDissect mapping: **Roster / reports / content**. Working search, sort, pagination, detail rows, column visibility and density.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/64-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/64-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/64.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/64-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/64-mobile.png)

### 65. Data Grid

- Reference: [Data Grid](https://themes.coderthemes.com/attex_mui/tables/data-grid)
- Layout: Component/example cards inside the shared shell.
- Components observed: Data Grid; Data; Full-featured CRUD; Column Grouping; Quick Filtering Grid; Column virtualization; Enabling Toolbar and exporting data; Column Visibility. 7 table surfaces; 0 chart surfaces.
- Form fields observed: Search (text); Select all rows (checkbox); Select row (checkbox); Unlabelled reference field (text); Search… (search).
- Intended interactions / labels: English; 1; Sort; Menu; Sort; Menu; Sort; Menu; Sort; Menu; Menu; Go to previous page; Go to next page; Add record; Sort; Menu; Sort; Menu.
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Data Grid; Data; Full-featured CRUD; Column Grouping; Quick Filtering Grid.
- AquaDissect mapping: **Roster / reports**. Applicable grid operations using existing table implementation; no spreadsheet editing or bulk operations without server support.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/65-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/65-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/65.json)

### 66. Area

- Reference: [Area](https://themes.coderthemes.com/attex_mui/ui/apex/area)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Area Charts; Basic Area Chart; Spline Area; Area Chart - Datetime X-axis; Area with Negative Values; Stacked Area; Irregular TimeSeries; Area Chart with Null values. 0 table surfaces; 7 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Zoom In, Reset Zoom and 1M accepted clicks; SVG/PNG/CSV menu opened. Exact axis/range changes and completed downloads unverified.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Area Charts; Basic Area Chart; Spline Area; Area Chart - Datetime X-axis; Area with Negative Values.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/66-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/66-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/66.json)

### 67. Bar

- Reference: [Bar](https://themes.coderthemes.com/attex_mui/ui/apex/bar)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Bar Charts; Basic Bar Chart; Grouped Bar Chart; Stacked Bar Chart; 100% Stacked Bar Chart; Bar with Negative Values; Reversed Bar Chart; Bar with Image Fill; Custom DataLabels Bar; Patterned Bar Chart; Bar with Markers. 0 table surfaces; 10 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Bar Charts; Basic Bar Chart; Grouped Bar Chart; Stacked Bar Chart; 100% Stacked Bar Chart.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/67-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/67-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/67.json)

### 68. Bubble

- Reference: [Bubble](https://themes.coderthemes.com/attex_mui/ui/apex/bubble)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Bubble Charts; Simple Bubble Chart; 3D Bubble Chart. 0 table surfaces; 2 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Bubble Charts; Simple Bubble Chart; 3D Bubble Chart; About; Support.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/68-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/68-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/68.json)

### 69. Candlestick

- Reference: [Candlestick](https://themes.coderthemes.com/attex_mui/ui/apex/candlestick)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Candlestick Charts; Simple Candlestick Chart; Category X-Axis; Candlestick with Line. 0 table surfaces; 3 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Candlestick Charts; Simple Candlestick Chart; Category X-Axis; Candlestick with Line; About.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/69-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/69-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/69.json)

### 70. Column

- Reference: [Column](https://themes.coderthemes.com/attex_mui/ui/apex/column)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Column Charts; Basic Column Chart; Column Chart with Datalabels; Stacked Column Chart; 100% Stacked Column Chart; Column with Markers; Column with Group Label; Column Chart with rotated labels & Annotations; Column Chart with negative values; Distributed Column Chart; Range Column Chart; Dynamic Loaded Chart. 0 table surfaces; 12 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Column Charts; Basic Column Chart; Column Chart with Datalabels; Stacked Column Chart; 100% Stacked Column Chart.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/70-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/70-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/70.json)

### 71. Heatmap

- Reference: [Heatmap](https://themes.coderthemes.com/attex_mui/ui/apex/heatmap)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Heatmap Charts; Basic Heatmap - Single Series; Heatmap - Multiple Series; Heatmap - Color Range; Heatmap - Range without Shades. 0 table surfaces; 4 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Heatmap Charts; Basic Heatmap - Single Series; Heatmap - Multiple Series; Heatmap - Color Range; Heatmap - Range without Shades.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/71-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/71-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/71.json)

### 72. Line

- Reference: [Line](https://themes.coderthemes.com/attex_mui/ui/apex/line)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Line Charts; Simple line chart; Line with Data Labels; Zoomable Timeseries; Line Chart with Annotations; Syncing charts; Gradient Line Chart; Missing / Null values; Dashed Line Chart; Stepline Chart; Brush Chart. 0 table surfaces; 12 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Line Charts; Simple line chart; Line with Data Labels; Zoomable Timeseries; Line Chart with Annotations.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/72-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/72-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/72.json)

### 73. Mixed

- Reference: [Mixed](https://themes.coderthemes.com/attex_mui/ui/apex/mixed)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Mixed Charts; Line & Column Chart; Multiple Y-Axis Chart; Line & Area Chart; Line, Column & Area Chart. 0 table surfaces; 4 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Mixed Charts; Line & Column Chart; Multiple Y-Axis Chart; Line & Area Chart; Line, Column & Area Chart.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/73-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/73-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/73.json)

### 74. Timeline

- Reference: [Timeline](https://themes.coderthemes.com/attex_mui/ui/apex/timeline)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Timeline Charts; Basic Timeline; Distributed Timeline; Multi Series Timeline; Advanced Timeline; Multiple Series - Group Rows. 0 table surfaces; 5 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Timeline Charts; Basic Timeline; Distributed Timeline; Multi Series Timeline; Advanced Timeline.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/74-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/74-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/74.json)

### 75. Boxplot

- Reference: [Boxplot](https://themes.coderthemes.com/attex_mui/ui/apex/boxplot)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Boxplot Charts; Basic Boxplot; Scatter Boxplot; Horizontal BoxPlot. 0 table surfaces; 3 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Boxplot Charts; Basic Boxplot; Scatter Boxplot; Horizontal BoxPlot; About.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/75-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/75-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/75.json)

### 76. Treemap

- Reference: [Treemap](https://themes.coderthemes.com/attex_mui/ui/apex/treemap)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Treemap Charts; Basic Treemap; Treemap Multiple Series; Distributed Treemap; Color Range Treemap. 0 table surfaces; 4 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Treemap Charts; Basic Treemap; Treemap Multiple Series; Distributed Treemap; Color Range Treemap.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/76-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/76-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/76.json)

### 77. Pie

- Reference: [Pie](https://themes.coderthemes.com/attex_mui/ui/apex/pie)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Pie Charts; Simple Pie Chart; Simple Donut Chart; Monochrome Pie Chart; Gradient Donut Chart; Patterned Donut Chart; Pie Chart with Image fill; Donut Update. 0 table surfaces; 7 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Pie Charts; Simple Pie Chart; Simple Donut Chart; Monochrome Pie Chart; Gradient Donut Chart.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/77-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/77-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/77.json)

### 78. Radar

- Reference: [Radar](https://themes.coderthemes.com/attex_mui/ui/apex/radar)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Radar Charts; Basic Radar Chart; Radar with Polygon-fill; Radar – Multiple Series. 0 table surfaces; 3 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Radar Charts; Basic Radar Chart; Radar with Polygon-fill; Radar – Multiple Series; About.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/78-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/78-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/78.json)

### 79. RadialBar

- Reference: [RadialBar](https://themes.coderthemes.com/attex_mui/ui/apex/radialbar)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: RadialBar Charts; Basic RadialBar Chart; Multiple RadialBars; Circle Chart - Custom Angle; Circle Chart with Image; Stroked Circular Guage; Gradient Circular Chart; Semi Circle Gauge. 0 table surfaces; 7 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: RadialBar Charts; Basic RadialBar Chart; Multiple RadialBars; Circle Chart - Custom Angle; Circle Chart with Image.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/79-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/79-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/79.json)

### 80. Scatter

- Reference: [Scatter](https://themes.coderthemes.com/attex_mui/ui/apex/scatter)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Scatter Charts; Scatter (XY) Chart; Scatter Chart - Datetime; Scatter - Images. 0 table surfaces; 3 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Scatter Charts; Scatter (XY) Chart; Scatter Chart - Datetime; Scatter - Images; About.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/80-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/80-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/80.json)

### 81. Polar Area

- Reference: [Polar Area](https://themes.coderthemes.com/attex_mui/ui/apex/polararea)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Polar Area Charts; Basic Polar Area Chart; Monochrome Polar Area. 0 table surfaces; 2 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Polar Area Charts; Basic Polar Area Chart; Monochrome Polar Area; About; Support.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/81-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/81-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/81.json)

### 82. Sparklines

- Reference: [Sparklines](https://themes.coderthemes.com/attex_mui/ui/apex/sparklines)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Sparklines Charts. 1 table surfaces; 11 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Sparklines Charts; About; Support; Contact Us.
- AquaDissect mapping: **Reports**. Specialized chart is not justified by current data; do not manufacture distributions, comparisons or market series. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/82-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/82-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/82.json)

### 83. Area

- Reference: [Area](https://themes.coderthemes.com/attex_mui/ui/chartjs/area)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Chartjs; Boundaries; Different Dataset; Draw Time; Stacked; Radar. 0 table surfaces; 5 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Chartjs; Boundaries; Different Dataset; Draw Time; Stacked.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/83-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/83-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/83.json)

### 84. Bar

- Reference: [Bar](https://themes.coderthemes.com/attex_mui/ui/chartjs/bar)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Chartjs; Border Radius; Floating; Horizontal; Stacked; Stacked with Groups; Vertical. 0 table surfaces; 6 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Chartjs; Border Radius; Floating; Horizontal; Stacked.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/84-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/84-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/84.json)

### 85. Line

- Reference: [Line](https://themes.coderthemes.com/attex_mui/ui/chartjs/line)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Chartjs; Interpolation; Line; Multi-Axes; Point Styling; Line Segment; Stepped. 0 table surfaces; 6 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Chartjs; Interpolation; Line; Multi-Axes; Point Styling.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/85-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/85-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/85.json)

### 86. Other

- Reference: [Other](https://themes.coderthemes.com/attex_mui/ui/chartjs/other)
- Layout: Chart cards with legends and plot surfaces; toolbar/range controls where present.
- Components observed: Chartjs; Bubble; Donut; Pie; Polar Area; Radar; Scatter; Stacked Bar/Line. 0 table surfaces; 7 chart surfaces.
- Form fields observed: Search (text).
- Intended interactions / labels: Inspect series, filter ranges, use toolbar and export where shown (affordances, not proof of operation)..
- Verified observation: Page content and visible controls were inspected at both sizes. No state-changing behavior was tested for this individual page; labels describe affordances only.
- Responsive observation: desktop 1536 × 674; mobile 390 × 844; no document horizontal overflow detected in captured viewport. Mobile visible headings: Chartjs; Bubble; Donut; Pie; Polar Area.
- AquaDissect mapping: **Reports**. Borrow readable axes/legends and filter patterns; implement actual record-count bars and scoped CSV. No chart library dependency added.
- Verification limits: No persistence/integration claim. Untested actions, hover tooltips and backend success/failure remain unverified.
- Evidence: [desktop DOM](../outputs/attex-study-2026-10-06/reference/86-desktop.txt), [mobile DOM](../outputs/attex-study-2026-10-06/reference/86-mobile.txt), [measurement](../outputs/attex-study-2026-10-06/reference/86.json), [desktop screenshot](../outputs/attex-study-2026-10-06/reference/86-desktop.png), [mobile screenshot](../outputs/attex-study-2026-10-06/reference/86-mobile.png)
