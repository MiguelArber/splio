# Splio

This is the project page for the Splio project developed for Drupal 8.

## Install instructions

1. Fetch the latest version of [Splio](https://gitlab.bodeboca.com/itguys/splio) and [bb_splio](https://gitlab.bodeboca.com/itguys/bb_splio) module from gitlab.
2. In D6, pull the latest version of Bodeboca from the branch [29-migrate-splio-to-valentina](https://gitlab.bodeboca.com/bodeboca/bodeboca/tree/29-migrate-splio-to-valentina).
3. In D8, pull the latest version Valentina from the branch [29-splio](https://gitlab.bodeboca.com/bodeboca/valentina/tree/29-splio).
4. Create a .key file to connect to Splio and configure a new key under the Key module.
5. Execute `drush entity-updates` and `drush cim` to import the configuration to your project.

## Typical workflow

After installation and configuration, Splio's typical workflow is meant to be as it follows:

1. A splio entity is created/updated/removed.
2. Splio's hook will catch the entity, make some processes and will queue it to process it later.
3. Before adding any element to the queue, an event is triggered in order to make possible custom changes.
3. On next cron run, the queued elements will be dequeued and processed.
4. Depending in the action (CRUD) that triggered the hook, the Splio module will perform several processes.
5. Before sending the request to Splio an event is triggered in order to make possible custom changes.
6. Data is sent to Splio via Splio API ver. 1.x.

## Testing instructions

I order to test this workflow the follow the steps below:

1. Create a new entity in Valentina:
```
    Action: POST
    URI: http://{{auth}}{{url}}/bb_data_layer/bodeboca_users?_format=json
    Body:
    [  
        {  
            "uid":172750,
            "domain":"bodeboca.com"
        }
    ]
```
2. Use Drush to process the queue by running `drush queue-run cron_splio_sync`.