# NPM Asset installer
Install all your npm assets via composer

### Installation
- install via composer `composer req slejnej/asset-installer`
- add asset installation script to your composer
    ```
      "scripts": {
        "post-install-cmd": [
          "slejnej\\AssetInstaller\\InstallerHandler::installAssets"
        ]
      }
    ```
- add npm packages direct to your composer
    ```
      "extra": {
        "npm": {
          "leaflet": "^1.7.1"
        }
      }
    ```
- run `composer install`

### In case
If this package is included in one of your vendor dependencies, then the _scripts_ are not run for security reasons. As a work around you can use in __primary__ `composer.json` something like this:
    ```
    find ./vendors -maxdepth 2 -type f -name composer.json -exec  bash -c "cat {} | grep ::installAssets && composer install --working-dir $(dirname {})" \;
    ```
It will find all the files that contain php command _installAsset_ and then execute `composer install` in containing folder.
Update `./vendors` folder where it should look in and adjust `maxdepth and update you `composer` executable.
