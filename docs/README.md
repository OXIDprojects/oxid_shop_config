# OXID Configurations import and export module 

Tools to export, backup and import OXID eShop shop settings via the built in oxid console.  

# Install

## Install using a local repository

* Create a local directory for oxps repositories in your project, e.g. `oxideshop/extensions/oxps/`.
* Check-out this module and move it to the directory you just created
* Add the repository to your project's compser.json, e.g. like this:

  ```json
    "repositories": {
        "oxid-professional-services/oxid-shop-config": {
            "type": "path",
            "url": "extensions/oxps/oxidshopconfig/"
        }
    }
  ```
## Install from VCS

* Require `oxid-professional-services/oxid-modules-config`

# Compatibility table


| OXID Eshop Version| OXID Modules Config Version | 
|-------------------|-----------------------------|
|6.2                | 0.1                         | 


