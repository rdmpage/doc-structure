<?php

error_reporting(E_ALL);

// Convert HOCR XHTML to OCR JSON

require_once('spatial.php');
require_once('common.php');


//----------------------------------------------------------------------------------------
function extract_box($text)
{
	$bbox = null;
	
	if (preg_match('/bbox (\d+) (\d+) (\d+) (\d+)/', $text, $m))
	{
		//print_r($m);
	
		$bbox = new BBox(
			$m[1], 
			$m[2],
			$m[3],
			$m[4]
			);
	}

	return $bbox;
}

//----------------------------------------------------------------------------------------
function extract_font_size($text)
{
	$size = -1;
	if (preg_match('/x_fsize (\d+)/', $text, $m))
	{
		$size = $m[1];
	}

	return $size;
}


//----------------------------------------------------------------------------------------
function parse_hocr($filename)
{
	$obj = new stdclass;

	$obj = new stdclass;
	$obj->pages = array();
	$obj->text_bbox = new BBox(0,0,0,0);

	$image_counter = 1;

	$page_count = 0;
	
	$line_counter = 0;
	
	$xml = file_get_contents($filename);
				
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

	$ocr_pages = $xpath->query ('//xhtml:div[@class="ocr_page"]');
	foreach($ocr_pages as $ocr_page)
	{
		// page level
		$page = new stdclass;	
		$page->type = 'page';
		$page->blocks = array();
		$page->images = array();
		
		// coordinates and other attributes 
		if ($ocr_page->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $ocr_page->attributes; 
		
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
		}
		
		if (isset($attributes['title']))
		{
			if (preg_match('/\/tmp\/(?<id>[^\/]+)\//', $attributes['title'], $m))
			{
				$source_id = $m['id'];
				$source_id = preg_replace('/_jp2$/', '', $source_id)			;
				$page->imageUrl = 'https://archive.org/download/' . $source_id . '/page/n' . $page_count . '.jpg';			
			}
		}
		//print_r($attributes);
	
		$page->bbox = extract_box($attributes['title']);
		$page->width = $page->bbox->maxx - $page->bbox->minx;
		$page->height= $page->bbox->maxy - $page->bbox->miny;
	
		$page->text_bbox = new BBox($page->width, $page->height, 0, 0);
	
		$line_counter = 0; // global line counter
				
		// images (these may be simply page numbers that haven't been recognised)
		foreach($xpath->query ('xhtml:div[@class="ocr_photo"]', $ocr_page) as $ocr_photo)
		{
			if ($ocr_photo->hasAttributes()) 
			{ 
				$attributes = array();
				$attrs = $line_tag->attributes; 

				foreach ($attrs as $i => $attr)
				{
					$attributes[$attr->name] = $attr->value; 
				}
			} 			
			$image_obj = new stdclass;
			$image_obj->bbox = $block->bbox;
			$page->images[] = $image_obj;
		}
	
		// text
		$ocr_careas = $xpath->query ('xhtml:div[@class="ocr_carea"]', $ocr_page);
		foreach($ocr_careas as $ocr_carea)
		{
			$ocr_pars = $xpath->query ('xhtml:p[@class="ocr_par"]', $ocr_carea);
			foreach($ocr_pars as $ocr_par)
			{		
				$block = new stdclass;
				$block->type = 'block';
				$block->bbox = new BBox($page->width, $page->height, 0, 0); 
				$block->tokens = array();
				$block->text_strings = array();
				$block->lines = array();
		
				$is_image = false;
		
				// hOCR can flag captions
				$lines = $xpath->query ('xhtml:span[@class="ocr_line" or "ocr_caption"]', $ocr_par);
				foreach($lines as $line_tag)
				{
					// coordinates
					if ($line_tag->hasAttributes()) 
					{ 
						$attributes = array();
						$attrs = $line_tag->attributes; 
	
						foreach ($attrs as $i => $attr)
						{
							$attributes[$attr->name] = $attr->value; 
						}
					}
					$line = new stdclass;
					$line->type = 'line';			
					$line->id = $line_counter++;	
					$line->tokens = array();
					$line->text_strings = array();
				
					$line->bbox = extract_box($attributes['title']);
	
					$block->bbox->merge($line->bbox);
							
					$words = $xpath->query ('xhtml:span[@class="ocrx_word"]', $line_tag);
					foreach($words as $word)
					{			
						if ($word->hasAttributes()) 
						{ 
							$attributes = array();
							$attrs = $word->attributes; 
		
							foreach ($attrs as $i => $attr)
							{
								$attributes[$attr->name] = $attr->value; 
							}
						}
				
						$token = new stdclass;
						$token->type = 'token';
						$token->bbox = extract_box($attributes['title']);
						
						// fonts
						$font_size = extract_font_size($attributes['title']);
						if ($font_size > -1)
						{
							$token->font_size = $font_size;
						}
					
						// this may actually be an image
						if (isset($token->font_size) && ($token->font_size > 100))
						{
							$is_image = true;
						}
					
						if ($is_image)
						{
							$image_obj = new stdclass;
							$image_obj->bbox = $token->bbox;
							$page->images[] = $image_obj;
							
							$block->type = 'image';
						}
						else
						{					
							$token->bbox->miny = $line->bbox->miny;
							$token->bbox->maxy = $line->bbox->maxy;
					
							$token->bold 	= false;
							$token->italic	= false;
					
							$token->font_size = extract_font_size($attributes['title']);
				
							$token->text = $word->firstChild->nodeValue;
				
							$line->tokens[] = $token;	
							$line->text_strings[] = $token->text;
							
							$block->tokens[] = $token;		
							$block->text_strings[] = $token->text;
						}
					}	
					
					$line->text = join(' ', $line->text_strings);
					unset($line->text_strings);
					
					$block->bbox->merge($line->bbox);
					
					if (!$is_image)	
					{				
						$block->lines[] = $line;
					}
	
				}
										
				// Grow the page bounding box
				$page->text_bbox->merge($block->bbox);
		
				// Get text for this block and cleanup
				$block->text = join(' ', $block->text_strings);
				unset($block->text_strings);
		
				// Add block to this page
				$page->blocks[] = $block;	
			}
		}
		
		$obj->text_bbox->merge($page->text_bbox);		
		$obj->pages[] = $page;		
		$page_count++;
	}
	
	return $obj;
}

$filename = 'insectakoreana371972_hocr.html';
$filename = 'biostor-273728_hocr.html';
$filename = 'acta-entomologica-sinica-1685_hocr.html';
$filename = 'konowia-9-0177-0190_hocr.html';
$filename = 'animal-systematics-evolution-and-diversity-37-3-225-228_hocr.html';

$obj = parse_hocr($filename);

//echo document_to_html($obj);

//echo document_to_text($obj);

echo document_to_json($obj);



?>