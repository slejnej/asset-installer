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
