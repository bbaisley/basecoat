<?php
/* Content::$messages->addInfo('Info Message from '.date('H:i:s')); */

Content::$page->add('title','Content, Templates &amp; Layouts');

$content	= new Content();

// Add route content to page
$content->processTemplate(Config::$routes[Core::$current_route]['template']);

$content->addToPage();

unset($content);
