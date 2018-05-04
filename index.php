<?php
	ini_set('memory_limit', '-1'); 
	include 'SpellCorrector.php';
	include('simple_html_dom.php');

	// make sure browsers see this page as utf-8 encoded HTML
	header('Content-Type: text/html; charset=utf-8');

	$limit = 10;
	$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
	$query = trim($query);

	$query_spliton_space = explode(" ", $query);
	$corrected_query = "";

	for($i = 0 ; $i < count($query_spliton_space) ; $i++){
		$eachterm = $query_spliton_space[$i];
		$corrected_query .= SpellCorrector::correct($eachterm);
	
		if(count($query_spliton_space) > 1 && $i < (count($query_spliton_space)-1))
			$corrected_query .= " ";
	}

	$results = false;
	$lucene_checked = "";
	$pagerank_checked = "";

	$spellcheck = $_REQUEST['checkspelling'] === "true" ? true : false;
	if($spellcheck) {
		$corrected_query = $query;
	}

	function displaySearchException($exp) {
		// in production you'd probably log or email this error to an admin
		// and then show a special message to the user but for this example
		// we're going to show the full exception
		die("<html>
			<head>
				<title>SEARCH EXCEPTION</title>
			</head>
			<body>
				<pre>{$exp->__toString()}</pre>
			</body>
		</html>");
	}

	// Read the URL from the CSV file, if it is missing in the solr results
	function readUrlFromCsv($docId) {
		$url = "";
		$handle = fopen("UrlToHtml_foxnews.csv", "r");
		if($handle !== FALSE) {
			$data = fgetcsv($handle);
			while($data !== FALSE) {
				$key = "/home/anumysore5/hw4/solr-7.2.1/crawl_data/HTML_files/HTML_files/".$data[0];
				if($key == $docId) {
					$url = $data[1];
					break;
				} else {
					continue;
				}
			}
		}
		fclose($handle);
		return $url;
	}

	if($query) {

		// The Apache Solr Client library should be on the include path
		// which is usually most easily accomplished by placing in the
		// same directory as this script ( . or current directory is a default
		// php include path entry in the php.ini)
		include_once("/var/www/html/solr-php-client-master/Apache/Solr/Service.php");

		// create a new solr service instance - host, port, and corename
		// path (all defaults in this example)
		$solr = new Apache_Solr_Service('localhost', 8983, '/solr/hw4core2');

		// if magic quotes is enabled then stripslashes will be needed
		if(get_magic_quotes_gpc() == 1) {
			$query = stripslashes($query);
		}

		// in production code you'll always want to use a try /catch for any
		// possible exceptions emitted  by searching (i.e. connection
		// problems or a query parsing error)
		if($_GET['algo'] == 'lucene') {
			try {
				$results = $solr->search($corrected_query, 0, $limit);
			} 
			catch(Exception $e) {
				displaySearchException($e);
			}
		} else if($_GET['algo'] == 'pagerank') {
			$additionalParameters = array(
				'sort' => 'pageRankFile desc'
			);
			try {
				$results = $solr->search($corrected_query, 0, $limit, $additionalParameters);
			}
			catch(Exception $e) {
				displaySearchException($e);
			}
		}
	}

?>

<?php
	// to retain the radio button selection after pressing the 'Submit Query' button
	if(!empty($_GET['algo'])) {
		if($_GET['algo'] == 'lucene') {
			$lucene_checked = "checked";
		} else if($_GET['algo'] == 'pagerank') {
			$pagerank_checked = "checked";
		}
	}
?>

<html>
	<head>
		<title>PHP Solr Client Example</title>

		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

		<style type="text/css">
    			a {text-decoration: none;}
			a:hover {text-decoration: underline;}
		</style>

		<script>
			$(document).ready(function() {
				$("#spellcorrectedword").click(function(){
					console.log('testing click on actual spelling');
					$("#checkspelling").val("true");
					$("#searchform").submit();
					$("#checkspelling").val("false");
				});

				$("#q").on("input", function(e) {
					var inputvalue = $(this).val().toLowerCase().trim();

					// If input query is empty, then don't do anything				
					if(inputvalue === "") return;

					// check if the input query is a multi-term query
					var inputqueryhasspace = (inputvalue.indexOf(" ") !== -1);
					var individualwords;

					// If input query has space(s), then it is a multi-term query. So get the individual terms.
					if(inputqueryhasspace) {
						individualwords = inputvalue.split(" ");
						inputvalue = individualwords[individualwords.length-1];
						individualwords.splice(individualwords.length-1, 1);
					}

					var regex = new RegExp("^[a-zA-Z0-9]+$");

					$.ajax( {
						url: "http://localhost:8983/solr/hw4core2/suggest?indent=on&wt=json",
						dataType: "jsonp",
						jsonp : 'json.wrf',
						data: {
							q: inputvalue
						},
						success: function( data ) {
							var dataList = $("#suggestions");
							dataList.empty();

							// get suggestions from solr
							var result = data.suggest.suggest[Object.keys(data.suggest.suggest)[0]].suggestions

							if(result.length) {
								for(var i=0, len=result.length; i<len; i++) {
									if(regex.test(result[i].term)) {
										var options;  
										if(inputqueryhasspace) {
											options = $("<option></option>").attr("value", individualwords.join(" ") + " " + result[i].term);
										} else {
											options = $("<option></option>").attr("value", result[i].term);
										}
										dataList.append(options);
									}
								}
							}
						} // end of success function
					} ); // end of ajax call
				}); // end of "on" function
			}); // end of document.ready function
		</script>
	</head>

	<body style="margin-left:20px; margin-right:400px">
		<form id="searchform" accept-charset="utf-8" method="get">
			<div style="margin-left:20px; margin-top:16px; margin-right:16px">
				<label for="q">Search:</label>
				<input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>" list="suggestions" placeholder="Search Here..." autocomplete="off"/>
				<datalist id="suggestions"></datalist>
			</div>

			<div style="margin-left:16px; margin-top:16px; margin-right:16px">
				<input <?php echo $lucene_checked; ?> type="radio" name="algo" value="lucene">Lucene Algorithm</input>
				<input <?php echo $pagerank_checked ;?> type="radio" name="algo" value="pagerank">PageRank Algorithm</input>
			</div>

			<div style="margin-left:16px; margin-top:16px; margin-right:16px">
				<input type="hidden" name="checkspelling" id="checkspelling" value="false">
				<input type="submit"/>
			</div>
		</form>

		<?php

		// display results
		if($results) {
			$total = (int) $results->response->numFound;
			$start = min(1, $total);
			$end = min($limit, $total);

			// If the input query is not same as the corrected query, then show results for the corrected query first. Also ask user if he wants to search for the misspelt word
			if(strcmp(strtolower($query),strtolower(trim($corrected_query))) !== 0) {
			?>
				<div style="margin-left:20px; font-size:20px"> Showing results for <b style="color:blue"><i> <?php echo " ". $corrected_query . " " ?> </i></b></div>
				<div style="margin-left:20px; font-size:16px"> Search instead for 
					<b style="color:blue"><a href="#" id="spellcorrectedword"> <?php echo " " . $query . " "?> </a></b>
				</div> 
			<?php
			} // end of actual query and corrected query string comparison

			if(!$results->response->docs) {
				echo '<div style="margin:20px">';
				echo '<div> Your search - <b>'.$query.'</b> - did not match any documents.</div>';
				echo '</br>';
				echo '<div> Suggestions: </div>';
				echo '<div><ul><li>Make sure all words are spelled correctly.</li>';
				echo '<li>Try different keywords.</li>';
				echo '<li>Try more general keywords.</li></ul></div>';
				echo '</div>';

			} else {
				echo '<div style="margin:20px">Results '.$start.' - '.$end.' of '.$total.':</div>';

				// iterate result documents
				foreach($results->response->docs as $doc) {
					$id = $doc->id;
					$title = $doc->og_title;
					$snippet = "... ...";

					//PHP treats NULL, false, 0, and the empty string as equal.
					if($doc->og_url == null) {
						$URL = readUrlFromCsv($id);					
					} else {
						$URL = $doc->og_url;
					}
				
					//$desc = ($doc->og_description !== null) ? $doc->og_description : "N/A";
			?>

					

			<?php
					$isSnippetFound = false;
					$urlStart = strrpos($id, '/') +1;
					$url = substr($id, $urlStart);
					$htmlFile = file_get_html('/home/anumysore5/hw4/solr-7.2.1/crawl_data/HTML_files/HTML_files/' . $url);

					if($htmlFile) {
						$htmlBody = $htmlFile->find('body');
						$flag1 = false;
						foreach($htmlBody as $body) {
							$allHtmlDivs = $body->find('div'); 
							foreach($allHtmlDivs as $div) {
								$divContent = $div->plaintext;
								if($flag1) {
									break;
								}

								// If the query string is found in the div's content
								if(strpos(strtolower($divContent), strtolower($corrected_query)) !== false) {
									$correctedQueryLength = strlen($corrected_query);
									$position = strpos(strtolower($divContent), strtolower($corrected_query));
									$highlightText = substr($divContent, $position, $correctedQueryLength);

									$explodeQuery = "/".$corrected_query."/i";
									//list($startPart, $endPart) = preg_split($explodeQuery, $line->plaintext);
									$snippetParts  = [];
									$snippetParts = preg_split($explodeQuery, $divContent);

									// Take the first half of the snippet from the 90 characters found before the query terms
									if($snippetParts[0]) {
										$startSnippet = substr($snippetParts[0], -90, 90);
									} else {
										$startSnippet = "";
									}

									// Take the second half of the snippet from the 90 characters found after the query terms
									if($snippetParts[1]) {
										$endSnippet = substr($snippetParts[1], 0, 90);
									} else {
										$endSnippet = "";
									}

									//$endSnip = substr($endPart, 0, 90);
									//$frontSnip = substr($startPart, -90, 90);

									if (preg_match('/(&gt|&lt|\/|\{|\}|\[|\]|\%|>|<|=)/i', $endSnippet) || 
										preg_match('/(&gt|&lt|\/|\{|\}|\[|\]|\%|>|<|=)/i', $startSnippet)) {
										continue;
									}

									$snippet = "...".$startSnippet."<b>".$highlightText."</b>".$endSnippet."...";
									$isSnippetFound = true;
									$flag1 = true;
								} // end of strpos
							} // end of foreach of allHtmlDivs
						} // end of foreach of htmlBody

						// If not, try to return the first sentence with atleast one query term in it
						if(!$isSnippetFound) {
							// If there is space in the query (i.e it is a multi-term query but the terms were not found together in any of the docs)
							if(strpos($corrected_query," ") !== false) {
								$queryTerms = explode(" ", $corrected_query);
								$flag2 = false;

								foreach($queryTerms as $term) {
									if($flag2) {
										break;
									}  

									$urlStart = strrpos($id, '/') +1;
									$url = substr($id, $urlStart);
									$htmlFile = file_get_html('/home/anumysore5/hw4/solr-7.2.1/crawl_data/HTML_files/HTML_files/' . $url);
									if($htmlFile) {
										$htmlBody = $htmlFile->find('body');
										$flag3=false;
										foreach($htmlBody as $body) {
											$allDivs = $body->find('div'); 
											foreach($allDivs as $div) {
												$divContent = $div->plaintext;
												if($flag3) {
													break;
												}

												if(strpos(strtolower($divContent), strtolower($term)) !== false) {
													$length = strlen($term);
													$position = strpos(strtolower($divContent), strtolower($term));
													$highlightText = substr($divContent, $position, $length);

													$explodeQuery = "/".$term."/i";
													list($startPart, $endPart) = preg_split($explodeQuery, $divContent);

													$endSnippet = substr($endPart, 0, 90);
													$startSnippet = substr($startPart, -90, 90);

													if (preg_match('/(&gt|&lt|\/|\{|\}|\[|\]|\%|>|<|=)/i', $endSnippet) || 
														preg_match('/(&gt|&lt|\/|\{|\}|\[|\]|\%|>|<|=)/i', $startSnippet)) {
														continue;
													}

													$snippet = "...".$startSnippet."<b>".$highlightText."</b>".$endSnippet."...";
													$flag3 = true;
													$flag2 = true;
												}
											}
										}
									}
								}
							}
						}
					}		
			?>
					<table style="margin:20px">
						<tr>
						<td><a class="hover" href= <?php echo $URL; ?> style="font-size:24px; color:blue; face:arial,sans-serif">
							<?php echo htmlspecialchars($title, ENT_NOQUOTES, 'utf-8'); ?></a></td>
						</tr>

						<tr>
						<td><a href= <?php echo $URL; ?> style="text-decoration:none; font-size:18px; color:green; face:arial,sans-serif">
							<?php echo htmlspecialchars($URL, ENT_NOQUOTES, 'utf-8'); ?></a></td>
						</tr>

						<tr>
						<td><?php echo htmlspecialchars($id, ENT_NOQUOTES, 'utf-8'); ?></td>
						</tr>

						<tr>
						<td><?php echo $snippet?></td>
						</tr>
					</table>
		<?php
				} // end of foreach loop
			} // end of else
		} // end of if($results)
		?>
			
	</body>
</html>
