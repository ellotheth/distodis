<?php

define('HEADER_SIG', 'HTTP_X_DISCOURSE_EVENT_SIGNATURE');
define('HEADER_EVENT', 'HTTP_X_DISCOURSE_EVENT');
define('HEADER_EVENT_TYPE', 'HTTP_X_DISCOURSE_EVENT_TYPE');

// https://github.com/discourse/discourse/blob/tests-passed/app/models/post.rb#L92-L97
define('POST_TYPE_REGULAR', 1);

// exit because $why with http status $status
function bork($status, $why) {
    echo htmlentities($why);
    http_response_code($status);
    exit();
}

// load the discourse topic with $id from the discourse api
function get_topic($id) {
    $query = http_build_query([
        'api_key' => API_KEY,
        'api_username' => API_USER,
    ]);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, TARGET.'/t/'.intval($id).'.json?'.$query);
    curl_setopt($curl, CURLOPT_HTTPGET, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Accept: application/json' ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    if (!($body = curl_exec($curl))) {
        bork(500, "something bad happened and i couldn't get the topic details");
    }

    if (!($json = json_decode($body, true))) {
        bork(500, json_last_error_msg());
    }

    return $json;
}

// create a post url
function post_url($slug, $id, $number = 1) {
    return TARGET."/t/$slug/$id/$number";
}

// create a url from a discourse icon template string
function icon_url($template, $size = 32) {
    return TARGET.str_replace('{size}', $size, $template);
}

// prepare raw post content for a discord embed
function summarize($content) {
    $content = strip_tags($content);
    if (strlen($content) >= 80) {
        $content = substr($content, 0, 77).'...';
    }

    return "_{$content}_";
}

// parse $post data and turn it into a discord embed
function transform($post) {
    if ($post['username'] === 'system') {
        bork(200, 'i ignore the system user');
    }

    if ($post['post_type'] !== POST_TYPE_REGULAR) {
        bork(200, 'i only do posts with content');
    }

    $topic = get_topic($post['topic_id']);
    if ($topic['archetype'] !== 'regular') {
        bork(200, 'i only do regular topics (not e.g. private messages)');
    }

    $action = $post['post_number'] === 1 ? 'posted' : 'replied to';

    return [
        'title' => $action.' '.$topic['title'].':',
        'url' => post_url($post['topic_slug'], $post['topic_id'], $post['post_number']),
        'description' => summarize($post['cooked']),
        'author' => [
            'name' => $post['display_username'],
            'icon_url' => icon_url($post['avatar_template']),
        ],
    ];
}

// the signature is required
if (!isset($_SERVER[HEADER_SIG])) {
    bork(400, 'your signature is missing');
}

// a POST body is also required
if (!($body = file_get_contents('php://input'))) {
    bork(400, 'wtf where is your body');
}

// if the signature is wrong, we're done
if (!hash_equals($_SERVER[HEADER_SIG], 'sha256='.hash_hmac('sha256', $body, SECRET))) {
    bork(401, 'newp.');
}

// response to ping requests from discourse, just to have something pretty
if (isset($_SERVER[HEADER_EVENT_TYPE]) && $_SERVER[HEADER_EVENT_TYPE] === 'ping') {
    bork(200, 'pong!');
}

// only posts get pushed to discord. (you'd think we'd want to push topics too, 
// but the first post in every topic also gets pushed as a post, and topics 
// don't have the post content.)
if (!isset($_SERVER[HEADER_EVENT]) || $_SERVER[HEADER_EVENT] !== 'post_created') {
    bork(200, "i don't care about this event");
}

// POST body has to be valid json with a 'post' object
if (!($json = json_decode($body, true))) {
    bork(400, json_last_error_msg());
}
if (!isset($json['post'])) {
    bork(200, "i only work with posts");
}

// parse the POST body into discord embed object
if (!($content = transform($json['post']))) {
    bork(500, 'i broke!');
}

// set up the discord object
$payload = json_encode([ 'embeds' => [ $content ]]);
var_dump($payload);

// send the post to discord
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, HOOK);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
if (!curl_exec($curl)) {
    bork(500, 'something bad happened, ohnoes');
}
