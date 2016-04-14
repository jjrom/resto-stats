# resto-stats

Statistics module for [resto](http://mapshup.com/resto/)

# Installation
## Resto module

Copy statistics.php file under include/resto/Modules folder of your resto instance

## Stats schema in resto database

Launch following script to add stats schema in resto database :

        _install/installDB.sh

Add -h to display options help.
Add -d <database name> to change database.
Add -F option to delete existing stats schema.

# Configuration

Add following lines to resto configuration file (include/config.php) in module section :

        'Statistics' => array(
            'activate' => true,
            'route' => 'stats',
            'options' => array(
                'schema' => 'stats'
            )
        ),

POST countries.geojson {resto_route}/{module_route}/stats/countries

# Routes
## Get

HTTP GET on {resto_route}/{module_route} (for example : https://localhost/resto/stats)

Route | Comments
----- | --------
/users | Statistics about users
/users/count/{field} | Get users number by distinct {field}
/users/countries/centroid | Get users number per country + country centroid
/users/countries/geometry | Get users number per country + country geometry
/users/{userid} | Statistics about user identified by {userid}
/downloads | Statistics about downloads
/downloads/best | Statistics about downloads
/downloads/products | Statistics about downloaded products
/downloads/recent | Statistics about recent downloads
/search | Statistics about search
/search/recent | Statistics about recent search
/search/best | Statistics about search products
/search/products | Statistics about search products by month
/insert | Statistics about insert
/insert/recent | Statistics about recent insert
/products | Statistics about products
/new/users | Statistics about new users
/new/users/count | Count users inscription by month