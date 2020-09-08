# Boxalino Shopware6 Data Exporter

## Introduction
For the Shopware6 integration, Boxalino comes with a divided approach: framework layer, data export layer and integration layer.
The current repository is used as a **data export layer** and includes the interfaces defined for Shopware6 export events.

By adding this package to your Shopware6 setup, your store data can be exported to Boxalino.
In order to use the API for generic functionalities (search, autocomplete, recommendations, etc), please check the integration repository
https://github.com/boxalino/rtux-integration-shopware

## Setup
1. Add the plugin to your project via composer
``composer require boxalino/exporter-shopware6``

2. Activate the plugin per Shopware use
``./bin/console plugin:refresh``
``./bin/console plugin:install --activate --clearCache BoxalinoExporter``
  
3. Log in your Shopware admin and configure the plugin with the configurations provided for your setup
Shopware Admin >> Settings >> System >> Plugins >> Boxalino Exporter

4. In order to kick off your account, a full export is required. 
For this, please set the exporter configuration per Sales Channel and disable the plugin where it is not in use.
The Headless channel must have the plugin disabled.
``./bin/console boxalino:exporter:run full``

5*. If the plugin configurations are not displayed, they can be accessed via direct link:
``admin#/sw/plugin/settings/BoxalinoExporter``

The exporter will create a _boxalino_ directory in your project where the temporary CSV files will be stored before the export;
The exporter will log it`s process in a dedicated log _./var/log/boxalino-exporter-<env>.log_ 

6. Proceed with the integration features available in our guidelines suggestions https://github.com/boxalino/rtux-integration-shopware

## Contact us!

If you have any question, just contact us at support@boxalino.com

*the marked features are not yet available
