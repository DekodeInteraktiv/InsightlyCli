#Insightly cli
## Setting up
1.  Clone the repository.
2.  Run `cd appc && composer install`
3. Copy `config-sample.php` to `config.php` and add the Insightly API key. See [here](https://support.insight.ly/hc/en-us/articles/204864594-Finding-or-resetting-your-API-key) on how to find it.
4. If you want to use every aspect of the `guess` command, you need the other APIs as well.

## Building
From the root folder of the projeect:

    sudo ./build.sh
 
You can now access the script from anywhere through the command `isc`. 

## Updating
    git pull
    cd app
    composer install
    cd ..
    sudo ./build
    

## Commands
To get information on available commands, run `isc help`.

## Tips and tricks
To avoid having to wait for cache rebuilding - cache is invalidated every hour - rebuild it in a cronjob by adding this to your crontab:

	*/30 * * * * isc rebuild-cache

## Extending
To make a new command, create it in the directory `includes/src/commands`. It must be a class extending the `Command` base class.

Implement all required methods, and then add it to the core in insightly-cli.php. To do that, add it to the array in the instantiation of the Core class in insightly-cli.php.

**Example:**

<pre>
$core = new \Dekode\InsightlyCli\Core( [
    new \Dekode\InsightlyCli\Commands\Find(),
    <b>new \Dekode\InsightlyCli\Commands\MyNewCommand()</b>
] );
</pre>

