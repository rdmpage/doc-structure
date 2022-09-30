<?php

error_reporting(E_ALL);

// Convert ABBYY XML to OCR JSON

require_once('spatial.php');
require_once('common.php');

//----------------------------------------------------------------------------------------
function parse_abbyy($filename)
{
	$obj = new stdclass;

	$obj = new stdclass;
	$obj->pages = array();
	$obj->text_bbox = null;


	$image_counter = 1;
	
	$page_count = 0;

	$xml = file_get_contents($filename);
				
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace('abbyy', 'http://www.abbyy.com/FineReader_xml/FineReader10-schema-v1.xml');

	$pages = $xpath->query ('//abbyy:page');
	foreach($pages as $xml_page)
	{
		// page level
		$page = new stdclass;	
		$page->type = 'page';
		$page->blocks = array();
		$page->images = array();

		// coordinates
		if ($xml_page->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $xml_page->attributes; 
		
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
		}
		
		$source_id = basename($filename, ".xml");
		$source_id = preg_replace('/_abbyy/', '', $source_id);
		$source_id = str_replace(' ', '', $source_id);
		$page->imageUrl = 'https://archive.org/download/' . $source_id . '/page/n' . $page_count . '.jpg';			
		//print_r($attributes);
		
	
		$page->width = $attributes['width'];
		$page->height = $attributes['height'];	
	
		$page->dpi = $attributes['resolution'];	
	
		$page->bbox = new BBox(0, 0, $page->width, $page->height);
		$page->text_bbox = new BBox($page->width, $page->height, 0, 0);
	
		$line_counter = 0; // global line counter
	
		$blocks = $xpath->query ('abbyy:block', $xml_page);
		foreach($blocks as $block)
		{
	
			// attributes
			if ($block->hasAttributes()) 
			{ 
				$attributes = array();
				$attrs = $block->attributes; 
		
				foreach ($attrs as $i => $attr)
				{
					$attributes[$attr->name] = $attr->value; 
				}
			}
	

			// what type of block?
			switch ($attributes['blockType'])
			{
				case 'Picture':
					$block->type = 'image';
					break;
		
				case 'Table':
					$block->type = 'table';
					break;		
		
				case 'Text':
				default:
					$block->type = 'text';
					break;
			}
		
			// images
			if ($block->type == 'image')
			{
				$image_obj = new stdclass;
				$image_obj->bbox = new BBox(
					$attributes['l'], 
					$attributes['t'],
					$attributes['r'],
					$attributes['b']
				);
				$image_obj->href = 'image-' . $image_counter . '.jpeg';
				$image_counter++;
				$page->images[] = $image_obj;
		
				/*
				$add_block = true;
			
			
				// Code below doesn't handle figures the size of the page. Actually we
				// need to test for overlap between blocks of text and images
			
					
				// There are some odd things that happen in ABBYY that we need to deal with
		
				// There may be an image almost the size of the page, this is the scan of the 
				// whole page and we don't want that.
			
				$width = $image_obj->bbox->maxx - $image_obj->bbox->minx;
				$height = $image_obj->bbox->maxy - $image_obj->bbox->miny;
			
				//echo '[' . $width . ',' . $height . ']' . "\n";
				//echo '[' . $page->width . ',' . $page->height . ']' . "\n";
			
				$ratio = ($width * $height) / ($page->width * $page->height);
			
				//echo $ratio . "\n";
			
				if ($ratio > 0.9)
				{
					$add_block = false;		
				}
		
				if ($add_block)
				{
					$image_counter++;
					$page->images[] = $image_obj;
				}	
				*/	
			}
		
		
			if ($block->type == 'table')
			{
				// huh?
				$table_obj = new stdclass;
				$table_obj->bbox = new BBox(
				$attributes['l'], 
				$attributes['t'],
				$attributes['r'],
				$attributes['b']
				);
		
				$page->tables[] = $table_obj;
			
			}

			if ($block->type == 'text')
			{		
				$pars = $xpath->query ('abbyy:text/abbyy:par', $block);
				foreach ($pars as $par)
				{
		
					$b = new stdclass;
					$b->type = 'block';
					$b->bbox = new BBox($page->width, $page->height, 0, 0); 
					$b->tokens = array();
					$b->text_strings = array();
			
					// Get lines of text
					$lines = $xpath->query ('abbyy:line', $par);
		
					foreach($lines as $line)
					{
			
						// coordinates
						if ($line->hasAttributes()) 
						{ 
							$attributes = array();
							$attrs = $line->attributes; 
		
							foreach ($attrs as $i => $attr)
							{
								$attributes[$attr->name] = $attr->value; 
							}
						}
				
						$text = new stdclass;
						$text->type = 'text';
	
						$text->id = $line_counter++;
	
						$text->bbox = new BBox(
							$attributes['l'], 
							$attributes['t'],
							$attributes['r'],
							$attributes['b']
							);
		
						$b->bbox->merge($text->bbox);
				
						// text
						$text->tokens = array();
								
						$formattings = $xpath->query ('abbyy:formatting', $line);
						foreach($formattings as $formatting)
						{
				
							if ($formatting->hasAttributes()) 
							{ 
								$attributes = array();
								$attrs = $formatting->attributes; 
			
								foreach ($attrs as $i => $attr)
								{
									$attributes[$attr->name] = $attr->value; 
								}
							}
				
							$bold 		= isset($attributes['bold']);
							$italic 	= isset($attributes['italic']);
							$font_size 	= $attributes['fs'];
							$font_name 	= $attributes['ff'];
					
							// pts to pixels
							$font_size *= $page->dpi / 72; 
				
							$nc = $xpath->query ('abbyy:charParams', $formatting);
					
							$token = null;
					
							$word = array();
					
							foreach($nc as $n)
							{
								// coordinates
								if ($n->hasAttributes()) 
								{ 
									$attributes = array();
									$attrs = $n->attributes; 
			
									foreach ($attrs as $i => $attr)
									{
										$attributes[$attr->name] = $attr->value; 
									}
								}
						
								if (0)
								{
									// take coordinates for this character 
									$char_box = new BBox(
										$attributes['l'], 
										$attributes['t'],
										$attributes['r'],
										$attributes['b']
										);	
								}
								else
								{
									// use line top and bottom to ensure smooth display of text
									$char_box = new BBox(
										$attributes['l'], 
										$text->bbox->miny,
										$attributes['r'],
										$text->bbox->maxy
										);			
								}		
					
								// If no token create one
								if ($token == null)					
								{
									$token = new stdclass;
									$token->type = 'token';
				
									$token->bbox = new BBox($page->width, $page->height, 0, 0); 			
				
									$token->bold 		= $bold;
									$token->italic		= $italic;
									$token->font_size 	= $font_size;
									$token->font_name 	= $font_name;	
							
									$token->word = array();								
								}
						
								$char = $n->firstChild->nodeValue;
												
								if ($char == ' ' && $token)
								{
									// if space then we have finished a word
						
									$token->text = join('', $token->word);
									$text->tokens[] = $token;	
									$b->tokens[] = $token;		
				
									$b->text_strings[] = $token->text;
						
									$token = null;
						
						
								}
								else
								{
									// grow word and bounding box
									$token->word[] = $char;
									$token->bbox->merge($char_box);
								}
						
							}
					
							if ($token)
							{
									$token->text = join('', $token->word);
									$text->tokens[] = $token;	
									$b->tokens[] = $token;		
				
									$b->text_strings[] = $token->text;
						
									$token = null;
					
							}
			
				
						}
			
					}
			
					// Grow the page bounding box
					$page->text_bbox->merge($b->bbox);
		
					// Get text for this block and cleanup
					$b->text = join(' ', $b->text_strings);
					unset($b->text_strings);			
			
					// Add block to this page
					$page->blocks[] = $b;
				}
			}
	
	
		}	
	
		$obj->pages[] = $page;
		$page_count++;
		
		if (!$obj->text_bbox)
		{
			$obj->text_bbox = $page->text_bbox;
		}
		else
		{
			$obj->text_bbox->merge($page->text_bbox);
		}		
		
	}

	return $obj;
}


$filename = 'blumea-0006-5196-59-006-009_abbyy.xml';

$filename = 'Australian Crickets_abbyy.xml';

$obj = parse_abbyy($filename);

echo document_to_html($obj);


?>