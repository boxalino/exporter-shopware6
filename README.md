# Boxalino Shopware6 Data Exporter

## Introduction
For the Shopware6 integration, Boxalino comes with a divided approach: framework layer, data export layer and integration layer.
The current repository is used as a **data export layer**.

By adding this package to your Shopware6 project, your setup can do the following:
 1. Run full data exports
 2. Run real-time data synchronizations (delta)

In order to create scheduled events, please check the integration repository for the guidelines:
https://github.com/boxalino/rtux-integration-shopware


## Setup
1. Add the plugin to your project via composer
``composer require boxalino/exporter-shopware6``

2. Activate the plugin per Shopware use
``./bin/console plugin:refresh``
``./bin/console plugin:install --activate --clearCache BoxalinoExporter``
  
3. Setup the [prerequisites](https://github.com/boxalino/exporter-shopware6/wiki) described in the wiki.

4. Log in your Shopware admin and configure the plugin with the configurations provided for your setup
Shopware Admin >> Settings >> System >> Plugins >> Boxalino Exporter

5. In order to kick off your account, a full export is required. 
For this, please set the exporter configuration per Sales Channel and disable the plugin where it is not in use.
The Headless channel must have the plugin disabled.
``./bin/console boxalino:exporter:run full``

6*. If the plugin configurations are not displayed, they can be accessed via direct link:
``admin#/sw/plugin/settings/BoxalinoExporter``

The exporter will create a _boxalino_ directory in your project where the temporary CSV files will be stored before the export;
The exporter will log it's process in a dedicated log _./var/log/boxalino-exporter-**env**.log_ 

6. Proceed with the integration features available in our guidelines suggestions https://github.com/boxalino/rtux-integration-shopware OR in [the package wiki](https://github.com/boxalino/exporter-shopware6/wiki)

## Contact us!

If you have any question, just contact us at support@boxalino.com
