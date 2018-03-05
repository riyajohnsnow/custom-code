<?php
/**
 * Template Name: Pricing Page
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
*/

get_header(); 
nectar_page_header($post->ID); 

//full page
$fp_options = nectar_get_full_page_options();
extract($fp_options);

?>
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Oswald" rel="stylesheet">


<section class="title-header">
    <div class="container-fluid spacer">
		<div class="row">
            <div class="col-md-8 header text-center">
                <h1>flexible pricing plans</h1>
            </div>

        </div>
    </div>
    <div class="container">
        <div class="row">
            <div class="post-box ">
                <div class="text1">
                    <h1>free</h1>
                </div>
                <div class="box">
                    <p>CORE Datasets-free download</p>
                    <p>CORE Datasets-free query</p>
                    <p>PREMIUM Datasets-Preview</p>
                    <p>PREMIUM Datasets-sample download</p>
                    <p>API access</p>
                </div>
                <div class="field-text">
                    <p>Usage</p>
                    <p>10 queries/min</p>
                    <p>10 queries/day</p>
                    <p>Non-Commercial Use</p>
                    <p>15 days</p></div>
                <div class="text-button">
                    <a class="btn btn-default btn-api text-center" href="#" role="button">Get
                        free API key
                    </a>
                </div>
            </div>
            <div class="post-box">
                <div class="text1">
                    <h1>subscribe to datasets/accelarator</h1>
                </div>
                <div class="box">
                    <p>12 Month Period</p>
                    <p class="underline">CORE and PREMIUM datasets</p>
                    <ul>
                        <li><a href="#"><span class="disc"></span>Updates</a> </li>
                        <li><a href="#"><span class="disc"></span>Enrichments</a> </li>
                        <li><a href="#"><span class="disc"></span>Linking</a> </li>
                        <li><a href="#"><span class="disc"></span>Bulk download</a> </li>
                        <li><a href="#"><span class="disc"></span>API query</a> </li>
                    </ul>
                    <div class="field-text1">
                    <p>Usage</p>
                    <p>25000 queries/month</p>
                    <p>50 queries/min</p>
                    <p>$0.10/extra query</p>
                    <p>&nbsp;</p>
                 	<p>$xxx/month</p>
                    </div>
                      <div class="text-button">
                          <a class="btn btn-default btn-api text-center" href="#"
                                                  role="button">Get started now
                          </a>
                      </div>
                </div>
            </div>
            <div class="post-box respo">
                <div class="text1">
                    <h1>Enterprise</h1>
                </div>
                <div class="box">
                    <ul>
                        <li><a href="#"><span class="disc"></span>You need a dataset that is not currently in your
                                log?</a> </li>
                        <li> &nbsp;  <a href="#">Let us know and we will prepare it for you!</a> </li>
                        <li><a href="#"><span class="disc"></span>Do you need entire catalog?</a> </li>
                        <li><a href="#">Let us know and we'll make a quick a download for u!</a> </li>
                        <li><a href="#"><span class="disc"></span>Do you have specific query need?</a> </li>
                        <li><a href="#">Let's discuss them!</a> </li>
                    </ul>
                    <div class="text-button1">
                        <a class="btn btn-default btn-api text-center" href="#" role="button">Contact us
                        </a>
                    </div>
                </div>
                </div>
             </div>
</section>


<?php get_footer(); ?>