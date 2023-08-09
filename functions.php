<?php
/**
 * Name: Automated Behaviors Test
 * Description: An automated accessibility scan.
 * Author: Equalify
 */

/**
 * WBA Fields
 */
function abt_fields(){

    $abt_fields = array(
        
        // These fields are added to the database.
        'db' => [

                // Meta values.
                'meta' => [
                    array(
                        'name'     => 'abt_uri',
                        'value'     => '',
                    )
                ]
            
        ],

        // These fields are HTML fields on the settings view.
        'settings' => [

            // Meta settings.
            'meta' => [
                array(
                    'name'     => 'abt_uri',
                    'label'    => 'abt-core URI (ie- https://abt.equalify.app/?url=)',
                    'type'     => 'text',
                )
            ]

        ]

    );

    // Return fields
    return $abt_fields;

}

/**
 * abt Tags
 */
function abt_tags(){

    // We don't know where helpers are being called, so we
    // have to set the directory if it isn't already set.
    if(!defined('__DIR__'))
        define('__DIR__', dirname(dirname(__FILE__)));
    
    // Read the JSON file - pulled from https://abt.webaim.org/api/docs?format=json
    $abt_tag_json = file_get_contents(__DIR__.'/abt_tags.json');
    $abt_tags = json_decode($abt_tag_json,true);

    // Convert abt format into Equalify format:
    // tags [ array('slug' => $value, 'name' => $value, 'description' => $value) ]
    $tags = array();
    if(!empty($abt_tags)){
        foreach($abt_tags as $abt_tag){

            // First, let's prepare the description, which is
            // the summary and guidelines.
            $description = '<p class="lead">'.$abt_tag['description'].'</p>';
            
            // Now lets put it all together into the Equalify format.
            array_push(
                $tags, array(
                    'title' => $abt_tag['title'],
                    'category' => $abt_tag['category'],
                    'description' => $description,

                    // abt-core uses periods, which get screwed up
                    // when equalify serializes them, so we're
                    // just not going to use periods
                    'slug' => str_replace('.', '', $abt_tag['slug'])

                )
            );

        }
    }

    // Return tags.
    return $tags;

}

 /**
  * abt URLs
  * Maps site URLs to abt URLs for processing.
  */
function abt_urls($page_url) {

    // Require abt_uri
    $abt_uri = DataAccess::get_meta_value('abt_uri');
    if(empty($abt_uri)){
        throw new Exception('abt-core URI is not entered. Please add the URI in the integration settings.');
    }else{
        return $abt_uri.$page_url;
    }

}

/**
 * abt Alerts
 * @param string response_body
 * @param string page_url
 */
function abt_alerts($response_body, $page_url){

    // Our goal is to return alerts.
    $abt_alerts = [];
    $abt_json = $response_body; 

    // Decode JSON.
    $abt_json_decoded = json_decode($abt_json);

    // Sometimes abt can't read the json.
    if(empty($abt_json_decoded)){

        // And add an alert.
        $alert = array(
            'source'  => 'abt',
            'url'     => $page_url,
            'message' => 'abt-core cannot reach the page.',
        );
        array_push($abt_alerts, $alert);

    }else{

        // We're add a lit of violations.
        $abt_violations = array();

        // Show abt violations
        foreach($abt_json_decoded[0]->violations as $violation){

            // Only show violations.
            $abt_violations[] = $violation;

        }

        // Add alerts.
        if(!empty($abt_violations)) {

            // Setup alert variables.
            foreach($abt_violations as $violation){

                // Default variables.
                $alert = array();
                $alert['source'] = 'abt';
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
                        $alert['tags'].= str_replace('.', '', 'abt_'.$tag);
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
                $abt_alerts[] = $alert;
                
            }

        }

    }
    // Return alerts.
    return $abt_alerts;

}
