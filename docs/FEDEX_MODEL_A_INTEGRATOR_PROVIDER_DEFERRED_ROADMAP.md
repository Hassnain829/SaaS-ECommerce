# FedEx Model A — Official Integrator Provider Roadmap

## Status

Deferred.

The platform is currently using **Model B — Merchant Credentials Mode** for the MVP.

Model A is the future official FedEx SaaS provider path where merchants connect FedEx directly through this platform without creating their own FedEx Developer Portal projects.

## Why Model A is deferred

FedEx confirmed by email that production keys require the Integrator Validation process.

Model A requires:

* FedEx Integrator Validation;
* Product Information Worksheet;
* Validation Cover Sheet;
* Additional Guidance requirements;
* GUI screenshots;
* FedEx EULA display and acceptance;
* Account Registration API;
* MFA/invoice validation;
* sandbox transaction JSON;
* FedEx review and approval;
* production keys issued only after validation approval.

The current platform is not ready to submit a complete FedEx validation package.

## Model A target architecture

The platform becomes an official FedEx Integrator Provider.

Flow:

1. Platform owner configures FedEx provider/parent credentials.
2. Merchant opens FedEx setup in the SaaS dashboard.
3. Merchant reads and accepts FedEx EULA inside the platform.
4. Merchant enters FedEx account number and account address.
5. Platform calls Account Registration API.
6. Merchant completes MFA or invoice validation.
7. FedEx returns child credentials.
8. Platform stores child credentials encrypted per merchant/store.
9. Platform uses merchant child credentials for rates, labels, tracking, and pickup.
10. FedEx billing stays with the merchant FedEx account.

## Required platform modules for Model A

### 1. FedEx provider parent credentials

* provider environment;
* parent API key/client ID encrypted;
* parent secret encrypted;
* production validation status;
* territories enabled;
* API products enabled.

### 2. FedEx EULA acceptance

* full EULA display inside the app;
* scroll-to-bottom requirement;
* checkbox acknowledgement;
* accept button;
* user/store/IP/user-agent audit;
* EULA version stored.

### 3. Account Registration API

* account number;
* account holder name;
* account address;
* country/state/postal validation;
* FedEx Factor 1 validation;
* FedEx transaction ID logging.

### 4. MFA / invoice validation

* SMS PIN;
* phone PIN;
* email PIN;
* invoice validation;
* retry handling;
* lockout handling;
* merchant-friendly failure states.

### 5. Child credential storage

* child key encrypted;
* child secret encrypted;
* account last4 only in UI;
* no raw secrets in logs;
* connection status per merchant/store.

### 6. FedEx APIs after connection

* Address Validation API;
* Service Availability API;
* Comprehensive Rates and Transit Times API;
* Ship API / labels;
* Tracking / visibility;
* Pickup Request API;
* Trade Documents API only if international shipping is supported.

### 7. Validation package builder

Admin/dev-only tool to export:

* GUI screenshots;
* EULA screenshots;
* end-customer registration flow screenshots;
* redacted JSON request/response files;
* rate test transaction evidence;
* label test transaction evidence;
* tracking screenshots;
* validation ZIP structure.

## Suggested future phases

### Future Phase A1 — FedEx Integrator Parent Credentials Foundation

Build parent credential storage, environment config, OAuth service, and admin-only validation status.

### Future Phase A2 — FedEx EULA Acceptance Flow

Build FedEx EULA screen, scroll requirement, checkbox, acceptance audit, and screenshot-ready UI.

### Future Phase A3 — FedEx Account Registration Factor 1

Build merchant FedEx account/address registration start and safe FedEx diagnostics.

### Future Phase A4 — FedEx MFA / Invoice Validation

Build SMS, phone, email, and invoice validation flows.

### Future Phase A5 — FedEx Child Credentials Storage

Store child credentials encrypted and mark merchant FedEx account connected.

### Future Phase A6 — FedEx Address/Service/Rate Validation Screens

Build sandbox validation screens and redacted JSON export.

### Future Phase A7 — FedEx Ship API Labels

Build label generation for PDF, PNG, and ZPL formats only after rates and registration are stable.

### Future Phase A8 — FedEx Tracking and Pickup

Build tracking refresh/timeline and pickup scheduling/cancellation.

### Future Phase A9 — FedEx Validation Package Submission

Complete PIW, Validation Cover Sheet, screenshots, JSON transactions, label artifacts if applicable, and email package to FedEx validation team.

## Current rule

Do not start Model A until Model B is polished and the Shipping & Delivery page is clean.

Current active FedEx path remains:

Model B — Merchant Credentials Mode.
