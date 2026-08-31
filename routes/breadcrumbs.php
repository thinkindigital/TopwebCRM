<?php

use Diglactic\Breadcrumbs\Generator;

/*
 * Admin panel breadcrumbs
 */

// Home
Breadcrumbs::for('admin', function (Generator $breadcrumbs) {
    $breadcrumbs->push(__('admin::app.layouts.dashboard'), route('admin.dashboard.index'));
});

// Settings
Breadcrumbs::for('settings', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.settings.title'), route('admin.settings.index'));
});

// Settings - Groups
Breadcrumbs::for('settings.groups', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.groups.index.title'), route('admin.settings.groups.index'));
});

Breadcrumbs::for('settings.groups.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.groups');
    $breadcrumbs->push(__('admin::app.settings.groups.index.create.title'), route('admin.settings.groups.create'));
});

Breadcrumbs::for('settings.groups.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.groups');
    $breadcrumbs->push(__('admin::app.settings.groups.index.edit.title'), route('admin.settings.groups.edit', ['id' => $id]));
});

// Settings - Types
Breadcrumbs::for('settings.types', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.types.index.title'), route('admin.settings.types.index'));
});

Breadcrumbs::for('settings.types.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.types');
    $breadcrumbs->push(__('admin::app.settings.types.index.create.title'), route('admin.settings.types.create'));
});

Breadcrumbs::for('settings.types.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.types');
    $breadcrumbs->push(__('admin::app.settings.types.index.edit.title'), route('admin.settings.types.edit', ['id' => $id]));
});

// Settings - Roles
Breadcrumbs::for('settings.roles', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.roles.index.title'), route('admin.settings.roles.index'));
});

Breadcrumbs::for('settings.roles.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.roles');
    $breadcrumbs->push(__('admin::app.settings.roles.create.title'), route('admin.settings.roles.create'));
});

Breadcrumbs::for('settings.roles.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.roles');
    $breadcrumbs->push(__('admin::app.settings.roles.edit.title'), route('admin.settings.roles.edit', ['id' => $id]));
});

// Settings - Users
Breadcrumbs::for('settings.users', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.users.index.title'), route('admin.settings.users.index'));
});

Breadcrumbs::for('settings.users.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.users');
    $breadcrumbs->push(__('admin::app.settings.users.index.create.title'), route('admin.settings.users.create'));
});

Breadcrumbs::for('settings.users.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.users');
    $breadcrumbs->push(__('admin::app.settings.users.index.edit.title'), route('admin.settings.users.edit', ['id' => $id]));
});

// Settings - Pipelines
Breadcrumbs::for('settings.pipelines', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.pipelines.index.title'), route('admin.settings.pipelines.index'));
});

Breadcrumbs::for('settings.pipelines.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.pipelines');
    $breadcrumbs->push(__('admin::app.settings.pipelines.create.title'), route('admin.settings.pipelines.create'));
});

Breadcrumbs::for('settings.pipelines.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.pipelines');
    $breadcrumbs->push(__('admin::app.settings.pipelines.edit.title'), route('admin.settings.pipelines.edit', ['id' => $id]));
});

// Settings - Sources
Breadcrumbs::for('settings.sources', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.sources.index.title'), route('admin.settings.sources.index'));
});

Breadcrumbs::for('settings.sources.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.sources');
    $breadcrumbs->push(__('admin::app.settings.sources.index.create.title'), route('admin.settings.sources.create'));
});

Breadcrumbs::for('settings.sources.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.sources');
    $breadcrumbs->push(__('admin::app.settings.sources.index.edit.title'), route('admin.settings.sources.edit', ['id' => $id]));
});

// Settings - Attributes
Breadcrumbs::for('settings.attributes', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.attributes.index.title'), route('admin.settings.attributes.index'));
});

Breadcrumbs::for('settings.attributes.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.attributes');
    $breadcrumbs->push(__('admin::app.settings.attributes.create.title'), route('admin.settings.attributes.create'));
});

Breadcrumbs::for('settings.attributes.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.attributes');
    $breadcrumbs->push(__('admin::app.settings.attributes.edit.title'), route('admin.settings.attributes.edit', ['id' => $id]));
});

// Settings - Warehouses
Breadcrumbs::for('settings.warehouses', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.warehouses.index.title'), route('admin.settings.warehouses.index'));
});

Breadcrumbs::for('settings.warehouses.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.warehouses');
    $breadcrumbs->push(__('admin::app.settings.warehouses.create.title'), route('admin.settings.warehouses.create'));
});

Breadcrumbs::for('settings.warehouses.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.warehouses');
    $breadcrumbs->push(__('admin::app.settings.warehouses.edit.title'), route('admin.settings.warehouses.edit', ['id' => $id]));
});

Breadcrumbs::for('settings.warehouses.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.warehouses');
    $breadcrumbs->push(__('admin::app.layouts.warehouses'), route('admin.settings.warehouses.view', ['id' => $id]));
});

// Settings - Email Templates
Breadcrumbs::for('settings.email_templates', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.email-template.index.title'), route('admin.settings.email_templates.index'));
});

Breadcrumbs::for('settings.email_templates.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.email_templates');
    $breadcrumbs->push(__('admin::app.settings.email-template.create.title'), route('admin.settings.email_templates.create'));
});

Breadcrumbs::for('settings.email_templates.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.email_templates');
    $breadcrumbs->push(__('admin::app.settings.email-template.edit.title'), route('admin.settings.email_templates.edit', ['id' => $id]));
});

// Settings - Web Forms
Breadcrumbs::for('settings.web_forms', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.webforms.index.title'), route('admin.settings.web_forms.index'));
});

Breadcrumbs::for('settings.web_forms.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.web_forms');
    $breadcrumbs->push(__('admin::app.settings.webforms.create.title'), route('admin.settings.web_forms.create'));
});

Breadcrumbs::for('settings.web_forms.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.web_forms');
    $breadcrumbs->push(__('admin::app.settings.webforms.edit.title'), route('admin.settings.web_forms.edit', ['id' => $id]));
});

// Settings - Webhooks
Breadcrumbs::for('settings.webhooks', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.webhooks.index.title'), route('admin.settings.webhooks.index'));
});

Breadcrumbs::for('settings.webhooks.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.webhooks');
    $breadcrumbs->push(__('admin::app.settings.webhooks.create.title'), route('admin.settings.webhooks.create'));
});

Breadcrumbs::for('settings.webhooks.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.webhooks');
    $breadcrumbs->push(__('admin::app.settings.webhooks.edit.title'), route('admin.settings.webhooks.edit', ['id' => $id]));
});

// Settings - Tags
Breadcrumbs::for('settings.tags', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.tags.index.title'), route('admin.settings.tags.index'));
});

// Settings - Workflows
Breadcrumbs::for('settings.workflows', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.workflows.index.title'), route('admin.settings.workflows.index'));
});

Breadcrumbs::for('settings.workflows.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.workflows');
    $breadcrumbs->push(__('admin::app.settings.workflows.create.title'), route('admin.settings.workflows.create'));
});

Breadcrumbs::for('settings.workflows.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.workflows');
    $breadcrumbs->push(__('admin::app.settings.workflows.edit.title'), route('admin.settings.workflows.edit', ['id' => $id]));
});

// Settings - Marketing Events
Breadcrumbs::for('settings.marketing.events', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.marketing.events.index.title'), route('admin.settings.marketing.events.index'));
});

Breadcrumbs::for('settings.marketing.events.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.marketing.events');
    $breadcrumbs->push(__('admin::app.settings.marketing.events.index.create.title'), route('admin.settings.marketing.events.create'));
});

Breadcrumbs::for('settings.marketing.events.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.marketing.events');
    $breadcrumbs->push(__('admin::app.settings.marketing.events.index.edit.title'), route('admin.settings.marketing.events.edit', ['id' => $id]));
});

// Settings - Marketing Campaigns
Breadcrumbs::for('settings.marketing.campaigns', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.marketing.campaigns.index.title'), route('admin.settings.marketing.campaigns.index'));
});

Breadcrumbs::for('settings.marketing.campaigns.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.marketing.campaigns');
    $breadcrumbs->push(__('admin::app.settings.marketing.campaigns.index.create.title'), route('admin.settings.marketing.campaigns.create'));
});

Breadcrumbs::for('settings.marketing.campaigns.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.marketing.campaigns');
    $breadcrumbs->push(__('admin::app.settings.marketing.campaigns.index.edit.title'), route('admin.settings.marketing.campaigns.edit', ['id' => $id]));
});

// Settings - Data Transfer Imports
Breadcrumbs::for('settings.data_transfers', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.data-transfer.imports.index.title'), route('admin.settings.data_transfer.imports.index'));
});

Breadcrumbs::for('settings.data_transfers.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.data_transfers');
    $breadcrumbs->push(__('admin::app.settings.data-transfer.imports.create.title'), route('admin.settings.data_transfer.imports.create'));
});

Breadcrumbs::for('settings.data_transfers.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.data_transfers');
    $breadcrumbs->push(__('admin::app.settings.data-transfer.imports.edit.title'), route('admin.settings.data_transfer.imports.edit', ['id' => $id]));
});

// Leads
Breadcrumbs::for('leads', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.leads'), route('admin.leads.index'));
});

Breadcrumbs::for('leads.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.layouts.leads'), route('admin.leads.index'));
});

Breadcrumbs::for('leads.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.leads.create.title'), route('admin.leads.create'));
});

Breadcrumbs::for('leads.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.leads.edit.title'), route('admin.leads.edit', ['id' => $id]));
});

Breadcrumbs::for('leads.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.leads.view.title'), route('admin.leads.view', ['id' => $id]));
});

// Contacts - Persons
Breadcrumbs::for('contacts.persons', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.persons'), route('admin.contacts.persons.index'));
});

Breadcrumbs::for('contacts.persons.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(__('admin::app.layouts.persons'), route('admin.contacts.persons.index'));
});

Breadcrumbs::for('contacts.persons.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(__('admin::app.contacts.persons.create.title'), route('admin.contacts.persons.create'));
});

Breadcrumbs::for('contacts.persons.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(__('admin::app.contacts.persons.edit.title'), route('admin.contacts.persons.edit', ['id' => $id]));
});

Breadcrumbs::for('contacts.persons.view', function (Generator $breadcrumbs, $person) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(
        __('admin::app.contacts.persons.view.title', ['name' => strip_tags($person->name)]),
        route('admin.contacts.persons.view', ['id' => $person->id])
    );
});

// Contacts - Organizations
Breadcrumbs::for('contacts.organizations', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.organizations'), route('admin.contacts.organizations.index'));
});

Breadcrumbs::for('contacts.organizations.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.organizations');
    $breadcrumbs->push(__('admin::app.layouts.organizations'), route('admin.contacts.organizations.index'));
});

Breadcrumbs::for('contacts.organizations.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.organizations');
    $breadcrumbs->push(__('admin::app.contacts.organizations.create.title'), route('admin.contacts.organizations.create'));
});

Breadcrumbs::for('contacts.organizations.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('contacts.organizations');
    $breadcrumbs->push(__('admin::app.contacts.organizations.edit.title'), route('admin.contacts.organizations.edit', ['id' => $id]));
});

// Products
Breadcrumbs::for('products', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.products'), route('admin.products.index'));
});

Breadcrumbs::for('products.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.layouts.products'), route('admin.products.index'));
});

Breadcrumbs::for('products.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.products.create.title'), route('admin.products.create'));
});

Breadcrumbs::for('products.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.products.edit.title'), route('admin.products.edit', ['id' => $id]));
});

Breadcrumbs::for('products.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.layouts.products'), route('admin.products.view', ['id' => $id]));
});

// Quotes
Breadcrumbs::for('quotes', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.quotes'), route('admin.quotes.index'));
});

Breadcrumbs::for('quotes.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('quotes');
    $breadcrumbs->push(__('admin::app.layouts.quotes'), route('admin.quotes.index'));
});

Breadcrumbs::for('quotes.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('quotes');
    $breadcrumbs->push(__('admin::app.quotes.create.title'), route('admin.quotes.create'));
});

Breadcrumbs::for('quotes.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('quotes');
    $breadcrumbs->push(__('admin::app.quotes.edit.title'), route('admin.quotes.edit', ['id' => $id]));
});

// Activities
Breadcrumbs::for('activities', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.activities'), route('admin.activities.index'));
});

Breadcrumbs::for('activities.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('activities');
    $breadcrumbs->push(__('admin::app.layouts.activities'), route('admin.activities.index'));
});

Breadcrumbs::for('activities.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('activities');
    $breadcrumbs->push(__('admin::app.activities.edit.title'), route('admin.activities.edit', ['id' => $id]));
});

// TopwebChat
Breadcrumbs::for('topweb_chat.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('topweb_chat::app.menu.title'), route('admin.topweb_chat.index'));
});

Breadcrumbs::for('topweb_chat.show', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('topweb_chat.index');
    $breadcrumbs->push(__('topweb_chat::app.conversations.description'), route('admin.topweb_chat.show', ['conversation' => $id]));
});

Breadcrumbs::for('topweb_chat.settings.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('topweb_chat::app.settings.title'), route('admin.topweb_chat.settings.index'));
});

// Configuration
Breadcrumbs::for('configuration', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.configuration'), route('admin.configuration.index'));
});

Breadcrumbs::for('configuration.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('configuration');
    $breadcrumbs->push(__('admin::app.layouts.configuration'), route('admin.configuration.index'));
});

Breadcrumbs::for('configuration.search', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('configuration');
    $breadcrumbs->push(__('admin::app.layouts.configuration'), route('admin.configuration.search'));
});

Breadcrumbs::for('configuration.edit', function (Generator $breadcrumbs, $slug, $slug2 = null) {
    $breadcrumbs->parent('configuration');
    $breadcrumbs->push(__('admin::app.layouts.configuration'), route('admin.configuration.index', ['slug' => $slug, 'slug2' => $slug2]));
});

// Mail
Breadcrumbs::for('mail', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.layouts.mail.title'), route('admin.mail.index', ['route' => 'inbox']));
});

Breadcrumbs::for('settings.data_transfers.import', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.data_transfers');
    $breadcrumbs->push(__('admin::app.settings.data-transfer.imports.import.title'), route('admin.settings.data_transfer.imports.import', ['id' => $id]));
});

Breadcrumbs::for('mail.route', function (Generator $breadcrumbs, $route) {
    $breadcrumbs->parent('mail');
    $breadcrumbs->push(__('admin::app.mail.index.'.$route), route('admin.mail.index', ['route' => $route]));
});

Breadcrumbs::for('mail.route.view', function (Generator $breadcrumbs, $route, $email) {
    $breadcrumbs->parent('mail.route', $route);
    $breadcrumbs->push($email->subject ?? '', route('admin.mail.view', ['route' => $route, 'id' => $email->id]));
});

// Help
Breadcrumbs::for('help.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.help.index.title'), route('admin.help.index'));
});

// User Account
Breadcrumbs::for('dashboard.account.edit', function (Generator $breadcrumbs, $user) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.account.edit.title'), route('admin.user.account.edit', $user->id));
});
