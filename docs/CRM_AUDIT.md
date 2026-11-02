# Internal CRM presentation and workflow audit

## Scope and safety

Reviewed routed internal shell, role dashboard, shared resource renderer (contacts, orders, users, services and legacy modules), WhatsApp inbox, inquiry detail, reports, FAQ and customer-transfer templates. Existing ticket screens receive the shared internal design treatment; existing ticket workflow coverage was rerun. Login/public templates were not redesigned.

No application database was deleted, reset, migrated or seeded. No secrets were inspected. Data deletion remains pending explicit scope confirmation. Tests use the configured `relasiku_crm_test` database; the added tests require a `_test` database name, use transactions and roll back. No live WhatsApp sending or external delivery test was performed.

## Implemented

- Rebuilt internal shell with Sistem CRM branding, inline SVG navigation, role-specific groups, no empty CS settings group, inquiry-detail inbox highlighting and meaningful current-page semantics.
- Added internal-only design tokens and styling for cards, metrics, responsive tables, forms, detail lists, toolbars, empty states, focus outlines and reduced motion. Shared styles apply to FAQ, transfers and tickets as well as resource screens, without restyling login.
- Added skip navigation and mobile menu expanded state, Escape dismissal, close button, focus return, focus cycling and inert hidden navigation. Matched behavior to the existing 760px layout breakpoint.
- Replaced sparse dashboard with database-derived role metrics: CS-scoped customer/order totals and pending work; admin active users/staff/services and unassigned inquiries; owner operational totals and active-CS workload. Metrics explicitly describe their all-period/current-position scope.
- Removed task cards from primary dashboard. No primary task/opportunity navigation. Direct legacy routes remain compatible and display a legacy notice; backend opportunity conversion and historical records remain intact.
- Existing confirmed inquiries link directly to their order, avoiding the mandatory price/consent form when simply opening an existing order. New confirmations retain their existing validation and authorization.
- Inbox uses readable intent/status text and inquiry assignee names; handoff assignment selects the current assignee. Assignment of conversation and inquiry remains explicitly separate.
- Shared detail fields resolve owner/assignee/order/opportunity relations rather than displaying raw foreign keys. Static creation timestamp is honestly labeled as creation information, not an audit log.
- Removed dead `inquiries.link-contact` form reference; fallback renderer links to the supported inquiry verification screen.
- Report sales value excludes cancelled orders. UI documents creation/closure/resolution date semantics and that stage filtering applies to the historical table only. Removed task metric and standalone opportunity detail links from report presentation.

## Validation

- `php artisan test`: **54 passed, 761 assertions** (final run).
- `npm run build`: **passed**, Vite 6.4.4, production assets rebuilt.
- `npm test` in `baileys-demo`: **4 passed, 0 failed**.
- Added `tests/Feature/InternalPresentationTest.php`: role/route rendering, primary legacy-link absence, CS sidebar groups, existing-order reopen presentation, inquiry active navigation, contact owner display, creation-label honesty, contact transfer/order detail/edit rendering, and cancelled report value regression.
- An initial new test incorrectly assumed admin contact reads were forbidden. Inspection and existing behavior establish read-only admin access; corrected the test rather than changing authorization. Final suite is green.

## Residual limitations and follow-up

- No visual browser, device, screen-reader or end-to-end keyboard verification was performed. Responsive styling and menu logic require browser acceptance testing at narrow widths, zoom, long names and long table content.
- FAQ and transfer screens retain their existing page structure and receive shared styling; they are not wholesale feature rewrites. No claim of every individual populated form permutation being newly tested.
- Legacy task/opportunity CRUD routes remain accessible under existing authorization, explicitly marked legacy. Dashboard still prepares legacy task data for compatibility with existing tests/contracts, although it is not rendered as primary work. Removing the legacy backend requires separate migration planning.
- Reporting retains historical opportunity-based conversion semantics. It is not payment, recognized revenue or profit reporting. Cancelled orders are excluded from value but do not rewrite historical won/lost counts. The table uses creation dates while conversion metrics use closure dates; labels now explain this distinction.
- Existing admin read access is preserved; sidebar hiding is not an authorization mechanism. Backend policies and regression tests remain authoritative.
- Conversation and inquiry assignments are separate operations; no implicit synchronization was introduced. Dashboard WA workload is handed-off conversation count, not unread message count.
- Static creation metadata is not a complete audit timeline. A comprehensive activity viewer was not added.
- Some legacy/shared templates remain compressed and contain old terminology in historical contexts. Backend naming and existing route contracts were intentionally preserved.
- Existing confirmed-order navigation is fixed in the UI; the POST confirmation endpoint still requires price and consent, preserving its conversion contract.
- Data cleanup is **not performed**. Confirm entities, retention, backups and exact deletion scope before any destructive operation.
