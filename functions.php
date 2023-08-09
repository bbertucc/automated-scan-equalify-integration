<?php
/**
 * Name: Automated Scan
 * Description: An automated accessibility scan.
 * Author: Equalify
 */

/**
 * Automated Scan Fields
 */
function automated_scan_fields(){

    $automated_scan_fields = array(
        
        // These fields are added to the database.
        'db' => [

                // Meta values.
                'meta' => [
                    array(
                        'name'     => 'automated_scan_uri',
                        'value'     => '',
                    )
                ]
            
        ],

        // These fields are HTML fields on the settings view.
        'settings' => [

            // Meta settings.
            'meta' => [
                array(
                    'name'     => 'automated_scan_uri',
                    'label'    => 'Automate Scan URI (ie- https://auto.equalify.app/?url=)',
                    'type'     => 'text',
                )
            ]

        ]

    );

    // Return fields
    return $automated_scan_fields;

}

/**
 * automated_scan Tags
 */
function automated_scan_tags(){

    // We don't know where helpers are being called, so we
    // have to set the directory if it isn't already set.
    if(!defined('__DIR__'))
        define('__DIR__', dirname(dirname(__FILE__)));
    
    // Read the JSON file - pulled from https://automated_scan.webaim.org/api/docs?format=json
    $automated_scan_tag_json = file_get_contents(__DIR__.'/automated_scan_tags.json');
    $automated_scan_tags = json_decode($automated_scan_tag_json,true);

    // Convert automated_scan format into Equalify format:
    // tags [ array('slug' => $value, 'name' => $value, 'description' => $value) ]
    $tags = array();
    if(!empty($automated_scan_tags)){
        foreach($automated_scan_tags as $automated_scan_tag){

            // First, let's prepare the description, which is
            // the summary and guidelines.
            $description = '<p class="lead">'.$automated_scan_tag['description'].'</p>';
            
            // Now lets put it all together into the Equalify format.
            array_push(
                $tags, array(
                    'title' => $automated_scan_tag['title'],
                    'category' => $automated_scan_tag['category'],
                    'description' => $description,

                    // Automate Scan uses periods, which get screwed up
                    // when equalify serializes them, so we're
                    // just not going to use periods
                    'slug' => str_replace('.', '', $automated_scan_tag['slug'])

                )
            );

        }
    }

    // Return tags.
    return $tags;

}

 /**
  * Automate Scan URLs
  * Maps site URLs to automated_scan URLs for processing.
  */
function automated_scan_urls($page_url) {

    // Require Automate Scan URI
    $automated_scan_uri = DataAccess::get_meta_value('automated_scan_uri');
    if(empty($automated_scan_uri)){
        throw new Exception('Automate Scan URI is not entered. Please add the URI in the integration settings.');
    }else{
        return $automated_scan_uri.$page_url;
    }

}

/**
 * Automate Scan Alerts
 * @param string response_body
 * @param string page_url
 */
function automated_scan_alerts($response_body, $page_url){

    // Our goal is to return alerts.
    $automated_scan_alerts = [];
    $automated_scan_json = $response_body; 

    // Decode JSON.
    $automated_scan_json_decoded = json_decode($automated_scan_json);

    // Sometimes automated_scan can't read the json.
    if(empty($automated_scan_json_decoded)){

        // And add an alert.
        $alert = array(
            'source'  => 'automated_scan',
            'url'     => $page_url,
            'message' => 'Automate Scan cannot reach the page.',
        );
        array_push($automated_scan_alerts, $alert);

    }else{

        // We add a lit of violations.
        $automated_scan_violations = array();

        // Show automated_scan violations
        foreach($automated_scan_json_decoded[0]->violations as $violation){

            // Only show violations.
            $automated_scan_violations[] = $violation;

        }

        // Add alerts.
        if(!empty($automated_scan_violations)) {

            // Setup alert variables.
            foreach($automated_scan_violations as $violation){

                // Default variables.
                $alert = array();
                $alert['source'] = 'automated_scan';
                $alert['url'] = $page_url;

                // Setup tags.
                $alert['tags'] = '';
                if(!empty($violation->tags)){

                    // We need to get rid of periods so Equalify
                    // wont convert them to underscores and they
                    // need to be comma separated.
                    $tags = $violation->tags;
                    $copy = $tags;
                    foreach($tags as $tag){
                        $alert['tags'].= str_replace('.', '', 'automated_scan_'.$tag);
                        if (next($copy ))
                            $alert['tags'].= ',';
                    }
                }                

                // Setup message.
                $alert['message'] = '"'.$violation->id.'" violation: '.$violation->help;

                // Setup more info.
                $alert['more_info'] = '';
                if($violation->nodes)
                    $alert['more_info'] = $violation->nodes;

                // Push alert.
                $automated_scan_alerts[] = $alert;
                
            }

        }

    }
    // Return alerts.
    return $automated_scan_alerts;

}
