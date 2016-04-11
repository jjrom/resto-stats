# resto-stats

Statistics module for [resto](http://mapshup.com/resto/)

# Installation
Copy statistics.php file under include/resto/Modules folder of your resto instance

# Configuration
Add following lines to resto configuration file (include/config.php) in module section :

        'Statistics' => array(
            'activate' => true,
            'route' => 'stats', // module route is customizable
            'options' => array()
        ),

# Routes
## Get

* {resto_route}/{module_route}/users                                          |  Statistics about users
* {resto_route}/{module_route}/users/count/{field}                            |  Get users number by distinct {field}
* {resto_route}/{module_route}/users/countries/centroid                       |  Get users number per country + country centroid
* {resto_route}/{module_route}/users/countries/geometry                       |  Get users number per country + country geometry
* {resto_route}/{module_route}/users/{userid}                                 |  Statistics about user identified by {userid}
* {resto_route}/{module_route}/downloads                                      |  Statistics about downloads
* {resto_route}/{module_route}/downloads/best                                 |  Statistics about downloads
* {resto_route}/{module_route}/downloads/products                             |  Statistics about downloaded products
* {resto_route}/{module_route}/downloads/recent                               |  Statistics about recent downloads
* {resto_route}/{module_route}/search                                         |  Statistics about search
* {resto_route}/{module_route}/search/recent                                  |  Statistics about recent search
* {resto_route}/{module_route}/search/best                                    |  Statistics about search products
* {resto_route}/{module_route}/search/products                                |  Statistics about search products by month
* {resto_route}/{module_route}/insert                                         |  Statistics about insert
* {resto_route}/{module_route}/insert/recent                                  |  Statistics about recent insert
* {resto_route}/{module_route}/products                                       |  Statistics about products
* {resto_route}/{module_route}/new/users                                      |  Statistics about new users
* {resto_route}/{module_route}/new/users/count                                |  Count users inscription by month