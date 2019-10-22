This module provides integration with [Splio](https://www.splio.com), a popular customer platform that drives connected loyalty marketing across a great variety of channels.
Splio module makes it easy for webmasters and Drupal developers to manage and customize how your entities are synchronized with Splio's platform.
It provides a complete integration with Splio's API, allowing the site administrators to easily sent data to Splio's platform through and intuitive GUI.
In addition, the module comes ready to be extended/integrated with other services for more customized business needs.
More generally, Splio module aspires to simplify the task of keeping your customer's and sales data up-to-date with Splio. Just configure the module once, and forget about it!

## Core features

*  Manage and keep synchronized your Drupal entities with Splio.
*  Allows you to easily set up your Contacts, Products, Receipts, Order lines and Stores through an intuitive GUI.
*  Manage all your Splio entities, allowing you to add, configure and delete default and customized fields.
*  Lets you manage the newsletter lists to which your contacts are subscribed to.
*  Provides a set of services ready to be used by developers to extended and adjust the module to your business needs.
*  Provides two separated modules that supply an API/service to add contacts to the blacklist and trigger messages delivery to your customers.
*  Your credentials will be securely stored and managed by the [Key](https://www.drupal.org/project/key) module.

## Just getting started?

If so, check out the [Splio's overview page](https://www.splio.com/splio/) to learn about the services offered by the platorm. If you already have an account with Splio, setting up
this module is a really simple task. In case you already have a site and you are planning to get started with Splio, you may be interested to do a bulk data import to the platform before starting
to use this module.

## Requirements and dependencies

Splio currently depends on Drupal 8.x-1.x core version and the latest release of the [Key](https://www.drupal.org/project/key) module.

## Installation notes

1.  Create a .key file to store your Splio credentials.  If you don't have already your API key, you can [contact](https://www.splio.com/contact-us/) with the Splio team to learn how to obtain one.
    The file should contain the following data: 
    ```
    {
        "apiKey": "yourApiKey",
        "universe": "yourUniverse"
    }
    ```
2. In the Key module config page, setup your key as an authentication multivalue key.
3. Open the Splio settings under the Web services menu and configure your environment. Test your connection and save your setiings. You are ready to go!

**Note:** The 8.x-1.x release uses [version 1.9](https://webdocs.splio.com/resources/api/) of the Splio API. Splio is currently developing a new API version which improves certain features.
In the future is planned to migrate to this new API version keeping retrocompatibility.

## Module configuration

Once you are finished with the installation you can start configuring the sync between your entities and Splio.

Access the Splio entity configuration page under the Web services menu. In this page you can configure which of your entities is the counterpart of each of the Splio categories.
You can select a whole entity or a particular bundle. New configuration tabs will appear for the categories you just configured.

In each tab, you can map the relation between your entity fields and Splio fields. By default, a series of required fields will be auto-generated.
It is mandatory to configure the Key Field* for each category. If your categories have no custom fields in Splio and you create them in Drupal, they will generate automatically in Splio by the module.
At the end of the contacts page there is an extra form attached. This form allows you to configure the field that defines to which newsletters your contacts are subscribed to.

Splio module will record any change in your entities and will sync them with Splio on next cron run.

*Splio API uses the following fields as Key Fields: Contacts: email, Products, Receipts, Order lines, Stores: extid. You can contact the Splio team if you need to use other Key Fields rather than the
ones provided by default. On demand, a combination of various fields can be established as a Key Field by the Splio team, however this kind of Key Fields are not supported by this module.

## Extending the module

Splio module was designed not only to provide a simple interface for mapping entities between Drupal and Splio, but also to easily be extended by developers in case of having special needs. For that purpose,
Splio module provides a series of services and events described below:

### Services

### Events

There are three types of events that are triggered at different points of the execution of the Splio module. These events are useful for verifying what data will be synced with Splio and even allowing 
developers to alter the data before sending it to Splio.