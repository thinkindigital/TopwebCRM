<?php

use Diglactic\Breadcrumbs\Generator;

/*
 * Admin panel breadcrumbs
 */

// Home
Breadcrumbs::for('admin', function (Generator $breadcrumbs) {
    $breadcrumbs->push(__('admin::app.dashboard.title'), route('admin.dashboard.index'));
});

// Settings
Breadcrumbs::for('settings', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.settings.title'), route('admin.settings.index'));
});

// Settings - Groups
Breadcrumbs::for('settings.groups.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.groups.title'), route('admin.settings.groups.index'));
});

Breadcrumbs::for('settings.groups.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.groups.index');
    $breadcrumbs->push(__('admin::app.settings.groups.create'), route('admin.settings.groups.create'));
});

Breadcrumbs::for('settings.groups.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.groups.index');
    $breadcrumbs->push(__('admin::app.settings.groups.edit'), route('admin.settings.groups.edit', ['id' => $id]));
});

// Settings - Types
Breadcrumbs::for('settings.types.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.types.title'), route('admin.settings.types.index'));
});

Breadcrumbs::for('settings.types.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.types.index');
    $breadcrumbs->push(__('admin::app.settings.types.create'), route('admin.settings.types.create'));
});

Breadcrumbs::for('settings.types.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.types.index');
    $breadcrumbs->push(__('admin::app.settings.types.edit'), route('admin.settings.types.edit', ['id' => $id]));
});

// Settings - Roles
Breadcrumbs::for('settings.roles.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.roles.title'), route('admin.settings.roles.index'));
});

Breadcrumbs::for('settings.roles.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.roles.index');
    $breadcrumbs->push(__('admin::app.settings.roles.create'), route('admin.settings.roles.create'));
});

Breadcrumbs::for('settings.roles.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.roles.index');
    $breadcrumbs->push(__('admin::app.settings.roles.edit'), route('admin.settings.roles.edit', ['id' => $id]));
});

// Settings - Users
Breadcrumbs::for('settings.users.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.users.title'), route('admin.settings.users.index'));
});

Breadcrumbs::for('settings.users.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.users.index');
    $breadcrumbs->push(__('admin::app.settings.users.create'), route('admin.settings.users.create'));
});

Breadcrumbs::for('settings.users.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.users.index');
    $breadcrumbs->push(__('admin::app.settings.users.edit'), route('admin.settings.users.edit', ['id' => $id]));
});

// Settings - Pipelines
Breadcrumbs::for('settings.pipelines.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.pipelines.title'), route('admin.settings.pipelines.index'));
});

Breadcrumbs::for('settings.pipelines.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.pipelines.index');
    $breadcrumbs->push(__('admin::app.settings.pipelines.create'), route('admin.settings.pipelines.create'));
});

Breadcrumbs::for('settings.pipelines.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.pipelines.index');
    $breadcrumbs->push(__('admin::app.settings.pipelines.edit'), route('admin.settings.pipelines.edit', ['id' => $id]));
});

// Settings - Sources
Breadcrumbs::for('settings.sources.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.sources.title'), route('admin.settings.sources.index'));
});

Breadcrumbs::for('settings.sources.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.sources.index');
    $breadcrumbs->push(__('admin::app.settings.sources.create'), route('admin.settings.sources.create'));
});

Breadcrumbs::for('settings.sources.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.sources.index');
    $breadcrumbs->push(__('admin::app.settings.sources.edit'), route('admin.settings.sources.edit', ['id' => $id]));
});

// Settings - Attributes
Breadcrumbs::for('settings.attributes.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.attributes.title'), route('admin.settings.attributes.index'));
});

Breadcrumbs::for('settings.attributes.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.attributes.index');
    $breadcrumbs->push(__('admin::app.settings.attributes.create'), route('admin.settings.attributes.create'));
});

Breadcrumbs::for('settings.attributes.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.attributes.index');
    $breadcrumbs->push(__('admin::app.settings.attributes.edit'), route('admin.settings.attributes.edit', ['id' => $id]));
});

// Settings - Warehouses
Breadcrumbs::for('settings.warehouses.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.warehouses.title'), route('admin.settings.warehouses.index'));
});

Breadcrumbs::for('settings.warehouses.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.warehouses.index');
    $breadcrumbs->push(__('admin::app.settings.warehouses.create'), route('admin.settings.warehouses.create'));
});

Breadcrumbs::for('settings.warehouses.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.warehouses.index');
    $breadcrumbs->push(__('admin::app.settings.warehouses.edit'), route('admin.settings.warehouses.edit', ['id' => $id]));
});

Breadcrumbs::for('settings.warehouses.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.warehouses.index');
    $breadcrumbs->push(__('admin::app.settings.warehouses.view'), route('admin.settings.warehouses.view', ['id' => $id]));
});

// Settings - Locations
Breadcrumbs::for('settings.locations.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.locations.title'), route('admin.settings.locations.index'));
});

// Settings - Email Templates
Breadcrumbs::for('settings.email_templates.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.email_templates.title'), route('admin.settings.email_templates.index'));
});

Breadcrumbs::for('settings.email_templates.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.email_templates.index');
    $breadcrumbs->push(__('admin::app.settings.email_templates.create'), route('admin.settings.email_templates.create'));
});

Breadcrumbs::for('settings.email_templates.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.email_templates.index');
    $breadcrumbs->push(__('admin::app.settings.email_templates.edit'), route('admin.settings.email_templates.edit', ['id' => $id]));
});

// Settings - Web Forms
Breadcrumbs::for('settings.web_forms.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.web_forms.title'), route('admin.settings.web_forms.index'));
});

Breadcrumbs::for('settings.web_forms.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.web_forms.index');
    $breadcrumbs->push(__('admin::app.settings.web_forms.create'), route('admin.settings.web_forms.create'));
});

Breadcrumbs::for('settings.web_forms.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.web_forms.index');
    $breadcrumbs->push(__('admin::app.settings.web_forms.edit'), route('admin.settings.web_forms.edit', ['id' => $id]));
});

// Settings - Webhooks
Breadcrumbs::for('settings.webhooks.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.webhooks.title'), route('admin.settings.webhooks.index'));
});

Breadcrumbs::for('settings.webhooks.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.webhooks.index');
    $breadcrumbs->push(__('admin::app.settings.webhooks.create'), route('admin.settings.webhooks.create'));
});

Breadcrumbs::for('settings.webhooks.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.webhooks.index');
    $breadcrumbs->push(__('admin::app.settings.webhooks.edit'), route('admin.settings.webhooks.edit', ['id' => $id]));
});

// Settings - Tags
Breadcrumbs::for('settings.tags.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.tags.title'), route('admin.settings.tags.index'));
});

// Settings - Workflows
Breadcrumbs::for('settings.workflows.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.workflows.title'), route('admin.settings.workflows.index'));
});

Breadcrumbs::for('settings.workflows.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.workflows.index');
    $breadcrumbs->push(__('admin::app.settings.workflows.create'), route('admin.settings.workflows.create'));
});

Breadcrumbs::for('settings.workflows.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.workflows.index');
    $breadcrumbs->push(__('admin::app.settings.workflows.edit'), route('admin.settings.workflows.edit', ['id' => $id]));
});

// Settings - Marketing Events
Breadcrumbs::for('settings.marketing.events.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.marketing.events.title'), route('admin.settings.marketing.events.index'));
});

Breadcrumbs::for('settings.marketing.events.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.marketing.events.index');
    $breadcrumbs->push(__('admin::app.settings.marketing.events.create'), route('admin.settings.marketing.events.create'));
});

Breadcrumbs::for('settings.marketing.events.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.marketing.events.index');
    $breadcrumbs->push(__('admin::app.settings.marketing.events.edit'), route('admin.settings.marketing.events.edit', ['id' => $id]));
});

// Settings - Marketing Campaigns
Breadcrumbs::for('settings.marketing.campaigns.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.marketing.campaigns.title'), route('admin.settings.marketing.campaigns.index'));
});

Breadcrumbs::for('settings.marketing.campaigns.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.marketing.campaigns.index');
    $breadcrumbs->push(__('admin::app.settings.marketing.campaigns.create'), route('admin.settings.marketing.campaigns.create'));
});

Breadcrumbs::for('settings.marketing.campaigns.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.marketing.campaigns.index');
    $breadcrumbs->push(__('admin::app.settings.marketing.campaigns.edit'), route('admin.settings.marketing.campaigns.edit', ['id' => $id]));
});

// Settings - Data Transfer Imports
Breadcrumbs::for('settings.data_transfer.imports.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.settings.data_transfer.imports.title'), route('admin.settings.data_transfer.imports.index'));
});

Breadcrumbs::for('settings.data_transfer.imports.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings.data_transfer.imports.index');
    $breadcrumbs->push(__('admin::app.settings.data_transfer.imports.create'), route('admin.settings.data_transfer.imports.create'));
});

Breadcrumbs::for('settings.data_transfer.imports.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('settings.data_transfer.imports.index');
    $breadcrumbs->push(__('admin::app.settings.data_transfer.imports.edit'), route('admin.settings.data_transfer.imports.edit', ['id' => $id]));
});

// Leads
Breadcrumbs::for('leads', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.leads.title'), route('admin.leads.index'));
});

Breadcrumbs::for('leads.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.leads.title'), route('admin.leads.index'));
});

Breadcrumbs::for('leads.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.leads.create'), route('admin.leads.create'));
});

Breadcrumbs::for('leads.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.leads.edit'), route('admin.leads.edit', ['id' => $id]));
});

Breadcrumbs::for('leads.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('leads');
    $breadcrumbs->push(__('admin::app.leads.view'), route('admin.leads.view', ['id' => $id]));
});

// Contacts - Persons
Breadcrumbs::for('contacts.persons', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.contacts.persons.title'), route('admin.contacts.persons.index'));
});

Breadcrumbs::for('contacts.persons.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(__('admin::app.contacts.persons.title'), route('admin.contacts.persons.index'));
});

Breadcrumbs::for('contacts.persons.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(__('admin::app.contacts.persons.create'), route('admin.contacts.persons.create'));
});

Breadcrumbs::for('contacts.persons.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(__('admin::app.contacts.persons.edit'), route('admin.contacts.persons.edit', ['id' => $id]));
});

Breadcrumbs::for('contacts.persons.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('contacts.persons');
    $breadcrumbs->push(__('admin::app.contacts.persons.view'), route('admin.contacts.persons.view', ['id' => $id]));
});

// Contacts - Organizations
Breadcrumbs::for('contacts.organizations', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.contacts.organizations.title'), route('admin.contacts.organizations.index'));
});

Breadcrumbs::for('contacts.organizations.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.organizations');
    $breadcrumbs->push(__('admin::app.contacts.organizations.title'), route('admin.contacts.organizations.index'));
});

Breadcrumbs::for('contacts.organizations.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('contacts.organizations');
    $breadcrumbs->push(__('admin::app.contacts.organizations.create'), route('admin.contacts.organizations.create'));
});

Breadcrumbs::for('contacts.organizations.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('contacts.organizations');
    $breadcrumbs->push(__('admin::app.contacts.organizations.edit'), route('admin.contacts.organizations.edit', ['id' => $id]));
});

// Products
Breadcrumbs::for('products', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.products.title'), route('admin.products.index'));
});

Breadcrumbs::for('products.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.products.title'), route('admin.products.index'));
});

Breadcrumbs::for('products.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.products.create'), route('admin.products.create'));
});

Breadcrumbs::for('products.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.products.edit'), route('admin.products.edit', ['id' => $id]));
});

Breadcrumbs::for('products.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('products');
    $breadcrumbs->push(__('admin::app.products.view'), route('admin.products.view', ['id' => $id]));
});

// Quotes
Breadcrumbs::for('quotes', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.quotes.title'), route('admin.quotes.index'));
});

Breadcrumbs::for('quotes.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('quotes');
    $breadcrumbs->push(__('admin::app.quotes.title'), route('admin.quotes.index'));
});

Breadcrumbs::for('quotes.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('quotes');
    $breadcrumbs->push(__('admin::app.quotes.create'), route('admin.quotes.create'));
});

Breadcrumbs::for('quotes.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('quotes');
    $breadcrumbs->push(__('admin::app.quotes.edit'), route('admin.quotes.edit', ['id' => $id]));
});

// Activities
Breadcrumbs::for('activities', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.activities.title'), route('admin.activities.index'));
});

Breadcrumbs::for('activities.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('activities');
    $breadcrumbs->push(__('admin::app.activities.title'), route('admin.activities.index'));
});

Breadcrumbs::for('activities.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('activities');
    $breadcrumbs->push(__('admin::app.activities.create'), route('admin.activities.create'));
});

Breadcrumbs::for('activities.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('activities');
    $breadcrumbs->push(__('admin::app.activities.edit'), route('admin.activities.edit', ['id' => $id]));
});

// TopwebChat
Breadcrumbs::for('topweb_chat.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.topweb_chat.title'), route('admin.topweb_chat.index'));
});

Breadcrumbs::for('topweb_chat.show', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('topweb_chat.index');
    $breadcrumbs->push(__('admin::app.topweb_chat.show'), route('admin.topweb_chat.show', ['conversation' => $id]));
});

Breadcrumbs::for('topweb_chat.settings.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('settings');
    $breadcrumbs->push(__('admin::app.topweb_chat.settings.title'), route('admin.topweb_chat.settings.index'));
});

// Configuration
Breadcrumbs::for('configuration', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.configuration.title'), route('admin.configuration.index'));
});

Breadcrumbs::for('configuration.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('configuration');
    $breadcrumbs->push(__('admin::app.configuration.title'), route('admin.configuration.index'));
});

Breadcrumbs::for('configuration.search', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('configuration');
    $breadcrumbs->push(__('admin::app.configuration.search'), route('admin.configuration.search'));
});

Breadcrumbs::for('configuration.edit', function (Generator $breadcrumbs, $slug, $slug2 = null) {
    $breadcrumbs->parent('configuration');
    $breadcrumbs->push(__('admin::app.configuration.edit'), route('admin.configuration.index', ['slug' => $slug, 'slug2' => $slug2]));
});

// Mail
Breadcrumbs::for('mail.index', function (Generator $breadcrumbs, $route = null) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.mail.title'), route('admin.mail.index', ['route' => $route]));
});

Breadcrumbs::for('mail.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('mail.index');
    $breadcrumbs->push(__('admin::app.mail.create'), route('admin.mail.create'));
});

Breadcrumbs::for('mail.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('mail.index');
    $breadcrumbs->push(__('admin::app.mail.edit'), route('admin.mail.edit', ['id' => $id]));
});

Breadcrumbs::for('mail.view', function (Generator $breadcrumbs, $route, $id) {
    $breadcrumbs->parent('mail.index');
    $breadcrumbs->push(__('admin::app.mail.view'), route('admin.mail.view', ['route' => $route, 'id' => $id]));
});

// Help
Breadcrumbs::for('help.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.help.title'), route('admin.help.index'));
});

// DataGrid
Breadcrumbs::for('datagrid.look_up', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.datagrid.look_up'), route('admin.datagrid.look_up'));
});

Breadcrumbs::for('datagrid.saved_filters.index', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.datagrid.saved_filters.title'), route('admin.datagrid.saved_filters.index'));
});

Breadcrumbs::for('datagrid.saved_filters.create', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('datagrid.saved_filters.index');
    $breadcrumbs->push(__('admin::app.datagrid.saved_filters.create'), route('admin.datagrid.saved_filters.create'));
});

Breadcrumbs::for('datagrid.saved_filters.edit', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('datagrid.saved_filters.index');
    $breadcrumbs->push(__('admin::app.datagrid.saved_filters.edit'), route('admin.datagrid.saved_filters.edit', ['id' => $id]));
});

// User Account
Breadcrumbs::for('user.account.edit', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.user.account.edit'), route('admin.user.account.edit'));
});

// TinyMCE
Breadcrumbs::for('tinymce.upload', function (Generator $breadcrumbs) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.tinymce.upload'), route('admin.tinymce.upload'));
});

// Web Forms (Front)
Breadcrumbs::for('web_forms.view', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.web_forms.view'), route('admin.settings.web_forms.view', ['id' => $id]));
});

Breadcrumbs::for('web_forms.preview', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.web_forms.preview'), route('admin.settings.web_forms.preview', ['id' => $id]));
});

Breadcrumbs::for('web_forms.form_js', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.web_forms.form_js'), route('admin.settings.web_forms.form_js', ['id' => $id]));
});

Breadcrumbs::for('web_forms.form_store', function (Generator $breadcrumbs, $id) {
    $breadcrumbs->parent('admin');
    $breadcrumbs->push(__('admin::app.web_forms.form_store'), route('admin.settings.web_forms.form_store', ['id' => $id]));
});
