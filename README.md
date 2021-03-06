## INTRODUCTION

This module provides integration with [Splio](https://www.splio.com), a popular
customer platform that drives connected  loyalty marketing across a great
variety of channels. Splio module makes it easy for webmasters and Drupal
developers to manage and customize how your entities are synchronized with
Splio's platform. It provides a complete integration with Splio's API, allowing
the site administrators to easily sent data to Splio's platform through and
intuitive GUI. In addition, the module comes ready to be extended/integrated
with other services for more customized business needs. More generally, Splio
module aspires to simplify the task of keeping your customer's and sales data
up-to-date with Splio. Just configure the module once, and forget about it!

### CORE FEATURES

*  Manage and keep synchronized your Drupal entities with Splio.
*  Allows you to easily set up your Contacts, Products, Receipts, Order lines
   and Stores through an intuitive GUI.
*  Manage all your Splio entities, allowing you to add, configure and delete
   default and customized fields.
*  Lets you manage the newsletter lists your contacts are subscribed to.
*  Provides a set of services ready to be used by developers to extended and
   adjust the module to your business needs.
*  Provides two extra modules that supply features to manage the blacklist and
   the message delivery to your customers.
*  Your credentials will be securely stored and managed by the
   [Key](https://www.drupal.org/project/key) module.

### JUST GETTING STARTED?

If so, check out the [Splio's overview page](https://www.splio.com/splio/) to
learn about the services offered by the platform. If you already have an 
with Splio, setting up this module is a really simple task. In case you already
have a site and you are planning to get started with Splio, you may be
interested to do a bulk data import to the platform before starting to use this
module.

## REQUIREMENTS

Splio currently depends on Drupal 8.x-1.x core version and the latest release of
the [Key](https://www.drupal.org/project/key) module.

## INSTALLATION

1.  Create a .key file to store your Splio credentials.  If you don't have
already your API key, you can [contact](https://www.splio.com/contact-us/) with
the Splio team to learn how to obtain one.
    The file should contain the following data: 
    ```
    {
        "apiKey": "yourApiKey",
        "universe": "yourUniverse"
    }
    ```
2. In the Key module config page, setup your key as an authentication multivalue
key.
3. Open the Splio settings under the Web services menu and configure your
environment. Test your connection and save your settings. You are ready to go!

**Note:** The 8.x-1.x release uses
[version 1.9](https://webdocs.splio.com/resources/api/) of the Splio API. Splio
is currently developing a new API version which improves certain features. In
the future is planned to migrate to this new API version keeping
retro-compatibility.

## CONFIGURATION

Once you are finished with the installation you can start configuring the sync
between your entities and Splio.

Access the Splio entity configuration page under the Web services menu. In this
page you can configure which of your entities is the counterpart of each of the
Splio categories. You can select a whole entity or a particular bundle.
New configuration tabs will appear for the categories you just configured.

In each tab, you can map the relation between your entity fields and Splio
fields. By default, a series of required fields will be auto-generated. It is
mandatory to configure the Key Field* for each category. If your categories have
no custom fields in Splio and you create them in Drupal, they will generate
automatically in Splio by the module. At the end of the contacts page there is
an extra form attached. This form allows you to configure the field that defines
to which newsletters your contacts are subscribed to.

Splio module will record any change in your entities and will sync them with
Splio on next cron run.

**Note:** Splio API uses the following Key Fields: Contacts: email, Products,
Receipts, Order lines, Stores: extid. You can contact the Splio team if you need
to use other Key Fields rather than the ones provided by default. On demand,
a combination of various fields can be established as a Key Field by the Splio
team, however this kind of Key Fields are not supported by this module.

## EXTENDING THE MODULE

Splio module was designed not only to provide a simple interface for mapping
entities between Drupal and Splio, but also to easily be extended by developers
in case of having special needs. For that purpose, Splio module provides a
series of services and events described below:

### SERVICES

Splio module provides the following services that can be injected into other
classes in order to integrate them with Splio. These services allow developers
to add items to the Splio queue, immediately sync data with Splio, add users to
the Blacklist or fire an emailing trigger at a given time.

**SplioConnector**: Manages the data synchronization with Splio. Provides the
CRUD actions for any entity or set of entities received an other helper methods.

**SplioTriggerManager**: Manages the emailing triggers of the Splio platform.
Allows the user to trigger the delivery for a certain message
(REST: /splio/trigger).

**SplioBlacklistManager**: Manages the blacklist of the Splio platform.
Allows to check if an email address is blacklisted in Splio and add any email 
address to the Blacklist (REST: /splio/blacklist).

### EVENTS

There are three types of events that are triggered at different points of the
execution of the Splio module. These events are useful for verifying what data
will be synced with Splio and even allowing  developers to alter the data before
sending it to Splio.

**SplioQueueEvent**: Dispatches an event whenever an item is going to be added
to the module queue. This event allows the user to alter the content of the item
that will be queued (even preventing the item from queueing).

**SplioRequestEvent**: Dispatches an event right before a request to Splio is
sent. This event allows the user to alter the content of the entity that will be
synced with Splio.

**SplioResponseEvent**: Dispatches an event right after a response from Splio is
received. This event contains the Splio API response and the sent content.
