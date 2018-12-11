#Insightly cli
## Setting things up
1.  Clone the repository.
2.  Run `composer install`
3. Copy `config-sample.php` to `config.php` and add the Insightly API key. See [here](https://support.insight.ly/hc/en-us/articles/204864594-Finding-or-resetting-your-API-key) on how to find it.

## Commands
### Find
Finds information about a specific project.

Examples:
    
    php insightly.php find finansforbundet.no  
