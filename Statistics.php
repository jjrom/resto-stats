<?php

/*
 * Copyright 2014 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */


/* 
 *   Created on : 06 january 2016, 10:27:08
 *   Author     : remi.mourembles@capgemini.com
*/


/**
 * 
 * Statistics module
 * 
 * The aim of this module is giving endpoints to generate statistics about database usage and content.
 * 
 * Only admin user can access the following endpoints :
 * Note : {module_route} is the "route" value defines within module configuration (see config.php)
 *        By default {module_route} value is "statistics"
 * 
 *  -- GET
 * 
 *      {module_route}/users                                          |  Statistics about users
 *      {module_route}/users/count/{field}                            |  Get users number by distinct {field}
 *      {module_route}/users/countries/centroid                       |  Get users number per country + country centroid
 *      {module_route}/users/countries/geometry                       |  Get users number per country + country geometry
 *      {module_route}/users/{userid}                                 |  Statistics about user identified by {userid}
 *      {module_route}/downloads                                      |  Statistics about downloads
 *      {module_route}/downloads/best                                 |  Statistics about downloads
 *      {module_route}/downloads/products                             |  Statistics about downloaded products
 *      {module_route}/downloads/recent                               |  Statistics about recent downloads
 *      {module_route}/search                                         |  Statistics about search
 *      {module_route}/search/recent                                  |  Statistics about recent search
 *      {module_route}/search/best                                    |  Statistics about search products
 *      {module_route}/search/products                                |  Statistics about search products by month
 *      {module_route}/insert                                         |  Statistics about insert
 *      {module_route}/insert/recent                                  |  Statistics about recent insert
 *      {module_route}/products                                       |  Statistics about products
 *      {module_route}/new/users                                      |  Statistics about new users
 *      {module_route}/new/users/count                                |  Count users inscription by month
 * 
 */
class Statistics extends RestoModule {
    /*
     * Default schema in which are stored additionals tables
     */

    private $schema = false;

    /*
     * Number of months to search over in case of recent items search
     */
    private $recentMonthNumber = 10;

    /**
     * Constructor
     * 
     * @param RestoContext $context
     * @param RestoUser $user
     */
    public function __construct($context, $user) {
        parent::__construct($context, $user);
    }

    /**
     * Run module - this function should be called by Resto.php
     * 
     * @param array $segments : route segments
     * @param array $data : POST or PUT parameters
     * 
     * @return string : result from run process in the $context->outputFormat
     */
    public function run($segments, $data) {

        if (isset($this->options['database']) && isset($this->options['database']['schema'])) {
            $this->schema = $this->options['database']['schema'];
        }

        /*
         * Only administrators can access this module
         */
        if (!$this->user->isAdmin()) {
            //RestoLogUtil::httpError(403);
        }

        /*
         * Switch on HTTP methods
         */
        switch ($this->context->method) {
            case 'GET':
                return $this->processGET($segments, $data);
            case 'POST':
                return $this->processPOST($segments, $data);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process GET
     * 
     * @param array $segments
     * @return type
     */
    private function processGET($segments) {
        $segment = array_shift($segments);
        switch ($segment) {
            case 'users':
                return $this->GET_users($segments);
            case 'downloads':
                return $this->GET_downloads($segments);
            case 'search':
                return $this->GET_search($segments);
            case 'insert':
                return $this->GET_insert($segments);
            case 'products':
                return $this->GET_products($segments);
            case 'new':
                return $this->GET_new($segments);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process HTTP POST
     * 
     * @param array $segments
     * @return type
     */
    private function processPOST($segments) {
        // Input data for POST request
        $data = RestoUtil::readInputData($this->context->uploadDirectory);

        $segment = array_shift($segments);
        switch ($segment) {
            case 'countries':
                return $this->POST_countries($segments, $data);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Processe HTTP POST over countries
     * 
     * @param array $segments
     * @param array $data
     */
    private function POST_countries($segments, $data) {
        $segment = array_shift($segments);
        if ($segment) {
            RestoLogUtil::httpError(404);
        }

        $features = $data['features'];
        foreach ($features as $feature) {
            // Initialize columns array
            $wkt = RestoGeometryUtil::geoJSONGeometryToWKT($feature['geometry']);
            $extent = RestoGeometryUtil::getExtent($wkt);

            // Compute "in house centroid" to avoid -180/180 date line issue
            $factor = 1;
            if (abs($extent[2] - $extent[0]) >= 180) {
                $factor = -1;
            }

            $columns = array(
                'identifier' => '\'' . $feature['id'] . '\'',
                'name' => '\'' . $feature['properties']['name'] . '\'',
                'geometry' => 'ST_GeomFromText(\'' . $wkt . '\', 4326)',
                'centroid' => 'ST_GeomFromText(\'POINT(' . (($extent[2] + ($extent[0] * $factor)) / 2.0) . ' ' . (($extent[3] + $extent[1]) / 2.0) . ')\', 4326)'
            );

            try {

                /*
                 * Start transaction
                 */
                pg_query($this->context->dbDriver->dbh, 'BEGIN');

                /*
                 * Store feature
                 */
                pg_query($this->context->dbDriver->dbh, 'INSERT INTO ' . $this->schema . '.countries (' . join(',', array_keys($columns)) . ') VALUES (' . join(',', array_values($columns)) . ')');
                pg_query($this->context->dbDriver->dbh, 'COMMIT');
            } catch (Exception $e) {
                pg_query($this->context->dbDriver->dbh, 'ROLLBACK');
                RestoLogUtil::httpError(500, 'Feature ' . $feature['id'] . ' cannot be inserted in database');
            }
        }
    }

    /**
     * Process HTTP GET over users
     * 
     * @param array $segments
     * @return type
     */
    private function GET_users($segments) {
        $segment = array_shift($segments);
        switch ($segment) {
            case NULL:
                return $this->stats_users($segments);
            case 'count':
                return $this->stats_users_count($segments);
            case 'countries':
                return $this->stats_users_countries($segments);
            case 'downloads':
                return $this->stats_users_downloads($segments);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process HTTP GET over downloads
     * 
     * @param array $segments
     * @return type
     */
    private function GET_downloads($segments) {
        // 
        $segment = array_shift($segments);
        if ($segment == NULL) {

            $results = $this->countLogsByMonth('download');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('Downloads count by month by collection', array(
                                'downloads' => $results
                    ));
            }
        } else if ($segment === 'recent' && array_shift($segments) == NULL) {
            $results = $this->countLogsByRecentMonth('download');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('Downloads count by month by collection', array(
                                'downloads' => $results
                    ));
            }
        } else if ($segment === 'best' && array_shift($segments) == NULL) {
            $results = $this->countLogsByProduct('download');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('Downloads count by product sort by count', array(
                                'downloads' => $results
                    ));
            }
        } else if ($segment === 'products' && array_shift($segments) == NULL) {
            $results = $this->countLogsByProductByMonth('download');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('Downloads count by product by month', array(
                                'downloads' => $results
                    ));
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process HTTP GET over search
     * 
     * @param array $segments
     * @return type
     */
    private function GET_search($segments) {
        // 
        $segment = array_shift($segments);
        if ($segment == NULL) {

            $data = $this->countLogsByMonth('search');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $data);
                    break;
                default:
                    return RestoLogUtil::success('Search count by month by collection', array(
                                'search' => $data
                    ));
            }
        } else if ($segment === 'recent' && array_shift($segments) == NULL) {
            $data = $this->countLogsByRecentMonth('search');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $data);
                    break;
                default:
                    return RestoLogUtil::success('Search count by month by collection', array(
                                'search' => $data
                    ));
            }
        } else if ($segment === 'best' && array_shift($segments) == NULL) {
            $results = $this->countLogsByProduct('search');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('Search count by product sort by count', array(
                                'search' => $results
                    ));
            }
        } else if ($segment === 'products' && array_shift($segments) == NULL) {
            $results = $this->countLogsByProductByMonth('search');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('Search count by product by month', array(
                                'search' => $results
                    ));
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * Get insert
     * 
     * @param array $segments
     * @return type
     */
    private function GET_insert($segments) {
        $segment = array_shift($segments);

        // Nothing afert 'insert' in the URL
        if ($segment == NULL) {

            $data = $this->countLogsByMonth('insert');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $data);
                    break;
                default:
                    return RestoLogUtil::success('Insert count by month by collection', array(
                                'insert' => $data
                    ));
            }
        } else if ($segment === 'recent' && array_shift($segments) == NULL) {
            $data = $this->countLogsByRecentMonth('insert');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $data);
                    break;
                default:
                    return RestoLogUtil::success('Insert count by month by collection', array(
                                'insert' => $data
                    ));
            }
        } else if ($segment === 'best' && array_shift($segments) == NULL) {
            $results = $this->countLogsByProduct('insert');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('insert count by product sort by count', array(
                                'insert' => $results
                    ));
            }
        } else if ($segment === 'products' && array_shift($segments) == NULL) {
            $results = $this->countLogsByProductByMonth('insert');

            // By default, format is JSON
            $format = $this->context->outputFormat;
            switch ($format) {
                case 'csv':
                    $this->csvExport(null, $results);
                    break;
                default:
                    return RestoLogUtil::success('insert count by product by month', array(
                                'insert' => $results
                    ));
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    private function countLogsByUser($service) {
        // Get collection from URL
        $collection = array_key_exists('collection', $this->context->query) ? $this->context->query['collection'] : null;
        $mindate = array_key_exists('_mindate', $this->context->query) ? $this->context->query['_mindate'] : null;
        $maxdate = array_key_exists('_maxdate', $this->context->query) ? $this->context->query['_maxdate'] : null;
        $limit = array_key_exists('_limit', $this->context->query) ? $this->context->query['_limit'] : null;

        // Construct query
        $query = "SELECT email AS userid, count(email) AS count from usermanagement.history WHERE service = '" . $service . "' AND collection" . ($collection ? "='" . $collection . "'" : "<>'*'") . ($mindate ? " AND querytime > '" . $mindate . "'" : "") . ($maxdate ? " AND querytime < '" . $maxdate . "'" : "") . " GROUP BY userid ORDER BY count DESC " . ($limit ? " LIMIT " . $limit : "");

        // Return resutls
        return $this->context->dbDriver->fetch($this->context->dbDriver->query($query));
    }

    private function countLogsByProduct($service) {
        // Get collection from URL
        $collection = array_key_exists('collection', $this->context->query) ? $this->context->query['collection'] : null;
        $mindate = array_key_exists('_mindate', $this->context->query) ? $this->context->query['_mindate'] : null;
        $maxdate = array_key_exists('_maxdate', $this->context->query) ? $this->context->query['_maxdate'] : null;
        $limit = array_key_exists('_limit', $this->context->query) ? $this->context->query['_limit'] : null;

        // Construct query
        $query = "SELECT resourceid AS resourceid, collection AS collection, count(resourceid) AS count from usermanagement.history WHERE service = '" . $service . "' AND collection" . ($collection ? "='" . $collection . "'" : "<>'*'") . ($mindate ? " AND querytime > '" . $mindate . "'" : "") . ($maxdate ? " AND querytime < '" . $maxdate . "'" : "") . " GROUP BY resourceid, collection ORDER BY count DESC " . ($limit ? " LIMIT " . $limit : "");

        // Return resutls
        return $this->context->dbDriver->fetch($this->context->dbDriver->query($query));
    }

    private function countLogsByProductByMonth($service) {
        // Get collection from URL
        $collection = array_key_exists('collection', $this->context->query) ? $this->context->query['collection'] : null;
        $mindate = array_key_exists('_mindate', $this->context->query) ? $this->context->query['_mindate'] : null;
        $maxdate = array_key_exists('_maxdate', $this->context->query) ? $this->context->query['_maxdate'] : null;

        // Construct query
        $query = "SELECT to_char(querytime, 'YYYY-MM') AS date, resourceid AS resourceid, collection AS collection, count(querytime) AS count from usermanagement.history WHERE service = '" . $service . "' AND collection" . ($collection ? "='" . $collection . "'" : "<>'*'") . ($mindate ? " AND querytime > '" . $mindate . "'" : "") . ($maxdate ? " AND querytime < '" . $maxdate . "'" : "") . " GROUP BY to_char(querytime, 'YYYY-MM'), collection, resourceid ORDER BY date";

        // Return resutls
        return $this->context->dbDriver->fetch($this->context->dbDriver->query($query));
    }

    /**
     * Count logs by month
     * 
     * @param string $service
     * @return type
     */
    private function countLogsByMonth($service) {
        // Get collection from URL
        $collection = array_key_exists('collection', $this->context->query) ? $this->context->query['collection'] : null;

        // Construct query
        $query = "SELECT to_char(querytime, 'YYYY-MM') AS date, collection AS collection, count(querytime) AS count from usermanagement.history WHERE service = '" . $service . "' AND collection" . ($collection ? "='" . $collection . "'" : "<>'*'") . " GROUP BY to_char(querytime, 'YYYY-MM'), collection ORDER BY date";

        // Return resutls
        return $this->context->dbDriver->fetch($this->context->dbDriver->query($query));
    }

    /**
     * Count logs by recent month
     * 
     * @param string $service
     * @return type
     */
    private function countLogsByRecentMonth($service) {
        // Get collection from URL
        $collection = array_key_exists('collection', $this->context->query) ? $this->context->query['collection'] : null;

        // Construct query
        $query = "SELECT to_char(querytime, 'YYYY-MM') AS date, collection AS collection, count(querytime) AS count from usermanagement.history WHERE service = '" . $service . "' AND collection" . ($collection ? "='" . $collection . "'" : "<>'*'") . " AND querytime > now() - interval '" . $this->recentMonthNumber . " month' GROUP BY to_char(querytime, 'YYYY-MM'), collection ORDER BY date";

        // Return resutls
        return $this->context->dbDriver->fetch($this->context->dbDriver->query($query));
    }

    private function GET_products($segments) {
        // TODO : stats about products (world repartition...)
    }

    private function GET_new($segments) {
        $segment = array_shift($segments);
        switch ($segment) {
            case 'users':
                return $this->stats_new_users($segments);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    private function stats_users() {
        // Get filters 
        // TODO : set default filters in config.php
        $limit = array_key_exists('limit', $this->context->query) ? $this->context->query['limit'] : null;
        $offset = array_key_exists('offset', $this->context->query) ? $this->context->query['offset'] : null;
        $keywords = array_key_exists('keywords', $this->context->query) ? $this->context->query['keywords'] : null;
        $sortby = array_key_exists('sortby', $this->context->query) ? $this->context->query['sortby'] : null;

        // Get results from database
        $data = $this->context->dbDriver->get(RestoDatabaseDriver::USERS_PROFILES, array(
            'limit' => $limit,
            'offset' => $offset,
            'keywords' => $keywords
        ));

        // By default, format is JSON
        $format = $this->context->outputFormat;
        switch ($format) {
            case 'csv':
                $this->csvExport(null, $data);
                break;
            default:
                return RestoLogUtil::success('Profiles for all users', array(
                            'profiles' => $data
                ));
        }
    }

    /**
     * Get counts
     * 
     * @param type $segments
     * @return type
     */
    private function stats_users_count($segments) {
        $field = array_shift($segments);

        // Available fields for count
        $available_fields = array('country', 'organizationcountry', 'flags', 'topics');

        // Check if requested field is ok
        if (!in_array($field, $available_fields)) {
            RestoLogUtil::httpError(404);
        }

        // Get counts from database
        $results = $this->context->dbDriver->query('SELECT distinct ' . $field . ',count(' . $field . ') as count FROM usermanagement.users group by ' . $field);
        $counts = array();
        while ($count = pg_fetch_assoc($results)) {
            if ($count[$field]) {
                $counts[] = array($count[$field], $count['count']);
            }
        }

        // By default, format is JSON
        $format = $this->context->outputFormat;
        switch ($format) {
            case 'csv':
                $this->csvExport(array($field, 'count'), $counts);
                break;
            default:
                return RestoLogUtil::success('Count users for field ' . $field, array(
                            'counts' => $counts
                ));
        }
    }

    private function stats_users_downloads($segments) {
        $segment = array_shift($segments);
        switch ($segment) {
            case 'count':
                return $this->stats_users_downloads_count($segments);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    private function stats_users_downloads_count($segments) {
        $segment = array_shift($segments);
        switch ($segment) {
            case NULL:
                $results = $this->countLogsByUser('download');

                // By default, format is JSON
                $format = $this->context->outputFormat;
                switch ($format) {
                    case 'csv':
                        $this->csvExport(null, $results);
                        break;
                    default:
                        return RestoLogUtil::success('Downloads count by user sort by count', array(
                                    'downloads' => $results
                        ));
                }
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Get stats about users countries
     * 
     * @param array $segments
     * @return type
     */
    private function stats_users_countries($segments) {
        $segment = array_shift($segments);
        switch ($segment) {
            case 'geometry':
                return $this->stats_users_countries_geometry();
            case 'centroid':
                return $this->stats_users_countries_centroid();
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Get stats about users per countries, for each country get associated 
     * geometry.
     * 
     * @return type
     */
    private function stats_users_countries_geometry() {

        /*
         * This function requires statistics database schema installed
         */
        if (!$this->schema) {
            RestoLogUtil::httpError(404);
        }

        // Construct SQL request
        $request = "select $this->schema.countries.identifier as id, $this->schema.countries.name as name, count(usermanagement.users.country) as count, st_asgeojson($this->schema.countries.geometry) as geometry from usermanagement.users INNER JOIN $this->schema.countries ON usermanagement.users.country = lower($this->schema.countries.name) GROUP BY usermanagement.users.country, $this->schema.countries.identifier, $this->schema.countries.name, $this->schema.countries.geometry;";

        // Get results
        $data = $this->context->dbDriver->fetch(pg_query($this->context->dbDriver->dbh, $request));
        $final_features = array();

        /*
         * Construct output
         */
        $maxCount = 0;
        $minCount = 100000;
        foreach ($data as $feature) {
            $maxCount = ($feature['count'] > $maxCount) ? $feature['count'] : $maxCount;
            $minCount = ($feature['count'] < $minCount) ? $feature['count'] : $minCount;
        }

        $rank = ceil(($maxCount - $minCount) / 10);

        foreach ($data as $feature) {
            $final_feature = array(
                'type' => 'Feature',
                'id' => $feature['id'],
                'properties' => array(
                    'name' => $feature['name'],
                    'count' => $feature['count'],
                    'ranking' => floor(($feature['count'] - $minCount) / $rank)
                ),
                'geometry' => json_decode($feature['geometry'])
            );

            array_push($final_features, $final_feature);
        }

        // By default, format is JSON
        $format = $this->context->outputFormat;
        switch ($format) {
            case 'csv':
                $this->csvExport(array($field, 'count'), $final_features);
                break;
            default:
                /*
                  return RestoLogUtil::success('Users distribution over countries', array(
                  'FeatureCollection' => array(
                  'type' => 'FeatureCollection',
                  'features' => $final_features
                  )
                  ));
                 * */
                return array(
                    'type' => 'FeatureCollection',
                    'features' => $final_features
                );
        }
    }

    private function stats_users_countries_centroid() {

        /*
         * This function requires statistics database schema installed
         */
        if (!$this->schema) {
            RestoLogUtil::httpError(404);
        }

        $request = "select $this->schema.countries.identifier as id, $this->schema.countries.name as name, count(usermanagement.users.country) as count, st_asgeojson($this->schema.countries.centroid) as centroid from usermanagement.users INNER JOIN $this->schema.countries ON usermanagement.users.country = lower($this->schema.countries.name) GROUP BY usermanagement.users.country, $this->schema.countries.identifier, $this->schema.countries.name, $this->schema.countries.centroid;";

        $data = $this->context->dbDriver->fetch(pg_query($this->context->dbDriver->dbh, $request));
        $final_features = array();

        foreach ($data as $feature) {
            $final_feature = array(
                'type' => 'Feature',
                'id' => $feature['id'],
                'properties' => array(
                    'name' => $feature['name'],
                    'count' => $feature['count']
                ),
                'geometry' => json_decode($feature['centroid'])
            );
            array_push($final_features, $final_feature);
        }

        // By default, format is JSON
        $format = $this->context->outputFormat;
        switch ($format) {
            case 'csv':
                $this->csvExport(array('count'), $final_features);
                break;
            default:
                return array(
                    'type' => 'FeatureCollection',
                    'features' => $final_features
                );
        }
    }

    private function stats_new_users($segments) {
        $segment = array_shift($segments);
        switch ($segment) {
            case 'count':
                return $this->stats_new_users_count($segments);
            case NULL:
                return $this->stats_new_users_list();
            default:
                RestoLogUtil::httpError(404);
        }
    }

    private function stats_new_users_count($segments) {
        if ($segments != NULL) {
            RestoLogUtil::httpError(404);
        }

        // Construct query
        $query = "select to_char(registrationdate, 'YYYY-MM') AS date, count(registrationdate) as count from usermanagement.users WHERE registrationdate > now() - interval '" . $this->recentMonthNumber . " month' GROUP BY to_char(registrationdate, 'YYYY-MM')";
        // Return resutls
        $data = $this->context->dbDriver->fetch($this->context->dbDriver->query($query));

        // By default, format is JSON
        $format = $this->context->outputFormat;
        switch ($format) {
            case 'csv':
                $this->csvExport(null, $data);
                break;
            default:
                return RestoLogUtil::success('Users count by month by collection', array(
                            'count' => $data
                ));
        }
    }

    /**
     * Export data in CSV
     * 
     * @param array $columnsNames : array containing columns names
     * @param array $sources : should be an array of array
     */
    private function csvExport($columnsNames, $sources) {
        // header set
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=data.csv');

        // pointer file
        $fp = fopen('php://output', 'w');

        // First line of file will be columns names
        fputcsv($fp, $columnsNames);

        // loop over input sources
        foreach ($sources as $source) {
            // Warning : $sources as to be an array of arrays
            fputcsv($fp, $source);
        }

        // Close file
        fclose($fp);
    }

}
