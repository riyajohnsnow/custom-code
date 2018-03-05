<?php 

/* Template Name: Market Place */

get_header();


$shop_page_url = get_permalink( 8928 );
$modifiedposts_args = array('orderby' => 'modified', 'ignore_sticky_posts' => '1', 'post_type' => 'product', 'posts_per_page' => '10');

$modifiedposts_loop = new WP_Query( $modifiedposts_args );

?>
<div class="container-wrap">

<section class="body-structure">
    <div class="main-wrapper">

        <!--Welcome Heading-->
        <div class="welcome-heading">
            <div class="container">
                <h2 class="welcome-text">
                    Welcome to John Snow Labs, your provider of healthcare and life science data!

                </h2>
            </div>
        </div>
        <!--Welcome Heading-->

        <!--Service Section-->
        <div class="service-section">
            <div class="container">
                <div class="services">
                    <div class="service-heading margin-wrapper">
                        <p class="blue-heading text-center">
                            We give you turnkey data for analysis. <br />
                            Our data is already tested, optimized and customized in a <br /> ready to use format for your big data,
                            data science or visualization platform.
                        </p>
                    </div>
                    <div class="service-blocks col span_12" style="margin-right: 0;">
                        <div class="col span_4 text-center">
                            <div class="col-inner">
                                <div class="service-icon">
                                    <div class="img-hover">
                                        <span class="image image1"></span>
                                    </div>
                                </div>
                                <div class="service-text">
                                    <h5 class="blue-heading"> Curated By Experts</h5>
                                    <p>Every dataset is selected, cleaned, enriched & documented by a
                                    domain expert</p>
                                </div>
                            </div>
                        </div>
                        <div class="col span_4 text-center">
                            <div class="col-inner">
                                <div class="service-icon">
                                    <div class="img-hover">
                                        <span class="image image2"></span>
                                    </div>
                                </div>
                                <div class="service-text">
                                    <h5 class="blue-heading"> Big Data Optimized </h5>
                                    <p> Out of the box optimized data formats for R, Python, SASS, Hadoop,
                                        Spark, SQL & BI tools</p>
                                </div>
                            </div>
                        </div>
                        <div class="col span_4 text-center">
                            <div class="col-inner">
                                <div class="service-icon">
                                    <div class="img-hover">
                                        <span class="image image3"></span>
                                    </div>
                                </div>
                                <div class="service-text">
                                    <h5 class="blue-heading"> Rigorous Quality </h5>
                                    <p> Datasets are triple checked -- automatically and manually, to make
                                        sure that they are error-free and ready for production use</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="service-blocks col span_12">
                        <div class="col span_offset_2 span_4 text-center">
                            <div class="col-inner">
                                <div class="service-icon">
                                    <div class="img-hover">
                                        <span class="image image4"></span>
                                    </div>
                                </div>
                                <div class="service-text">
                                    <h5 class="blue-heading"> Always Up To Date </h5>
                                    <p>Daily updates, get automatic, versioned, clean & tested updates
                                        as they happen</p>
                                </div>
                            </div>
                        </div>
                        <div class="col span_4 text-center">
                            <div class="col-inner">
                                <div class="service-icon">
                                    <div class="img-hover">
                                        <span class="image image5"></span>
                                    </div>
                                </div>
                                <div class="service-text">
                                    <h5 class="blue-heading"> Clean & Interoperable </h5>
                                    <p>Unified and standards based data model -- including numbers, dates,
                                        units, currency, null values, identifiers & references</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--Service Section-->

        <!--FlipBoxes-->
        <div class="flipboxes">
            <div class="container">
                <div style=" color: #0d1d36;" class="span_4 col no-extra-padding">
                    <div class="vc_column-inner">
                        <div class="wpb_wrapper">
                            <div class="nectar-flip-box"
                                 data-min-height="300" data-flip-direction="horizontal-to-left" data-h_text_align="center"
                                 data-v_text_align="top">
                                <div class="flip-box-front" data-bg-overlay="true" data-text-color="light"
                                     style="background-color:rgb(31,189,242); min-height: 300px; height: auto;">
                                    <div class="inner">
                                        <h3>Explore Healthcare Repository</h3>
                                    </div>
                                </div>
                                <div class="flip-box-back" data-bg-overlay="true" data-text-color="light" style="background-color: rgb(25, 154, 216); min-height: 300px; height: auto;">
                                    <a href="<?php echo $shop_page_url; ?>?swoof=1&product_cat=health">
                                        <div class="inner">
                                            <h3 style="text-align: center;"><strong>Explore Healthcare Repository</strong></h3>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style=" color: #0d1d36;" class="span_4 col no-extra-padding">
                    <div class="vc_column-inner">
                        <div class="wpb_wrapper">
                            <div class="nectar-flip-box"
                                 data-min-height="300" data-flip-direction="horizontal-to-left" data-h_text_align="center"
                                 data-v_text_align="top">
                                <div class="flip-box-front" data-bg-overlay="true" data-text-color="light"
                                     style="background-color:rgb(31,189,242); min-height: 300px; height: auto;">
                                    <div class="inner">
                                        <h3>Explore Life Science Repository</h3>
                                    </div>
                                </div>
                                <div class="flip-box-back" data-bg-overlay="true" data-text-color="light" style="background-color: rgb(25, 154, 216); min-height: 300px; height: auto;">
                                    <a href="<?php echo $shop_page_url; ?>?swoof=1&product_cat=activities">
                                        <div class="inner">
                                            <h3 style="text-align: center;">
                                                <strong>Explore Life Science Repository</strong>
                                            </h3>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-layout span_4 col">
<!--                    <div class="border-box">-->
<!--                        <div class="box-wrapper text-center">-->
<!--                            <p class="service-text">Sign up today and save 50% of the regular price</p>-->
                           <div class="start-activity"> <a class="btn btn-started " href="<?php echo
                    get_permalink( get_option('woocommerce_myaccount_page_id') ); ?>" title="<?php _e('My Account','woothemes'); ?>">
                                Get Started Today
                            </a></div>
<!--                        </div>-->
<!--                    </div>-->
                </div>
            </div>
        </div>
        <!--FlipBoxes-->

        <!--Latest Datasets-->
        <div class="datasets margin-wrapper">
            <div class="container">
                <div class="col span_12">
                    <div class="col span_6">
                        <div class="border-heading">
                            <h5 class="blue-heading">Latest datasets</h5>
                        </div>
                        <div class="datasets-content">
                            <div class="content-wrapper">

                                <?php
                                while( $modifiedposts_loop->have_posts() ){
                                $modifiedposts_loop->the_post();
                                echo '<div class="dataset-text"> <i class="fa fa-database fa-2x"></i>';
                                echo '<span class="text"><a href="' . get_permalink( $modifiedposts_loop->post->ID ) . '"> ' .get_the_title( $modifiedposts_loop->post->ID ) . '</a></span></div>';
                                }?>

                                <!--<div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        APR-DRG Service Intensity Weights and Average Length of Stay 2017
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Hospital Medicare Cost Report Numeric Data 2016
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Medicare Spending Claims Based by Price Age Sex and Race State Level
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Provider Summary of Outpatient Prospective Payment System APC 2014
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Ambulatory SurgicalCenter Quality Reporting by Facility
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Outpatient Imaging Efficiency Core Measures by State
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Home Health Care Agencies List and Ratings
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Nursing Home Compare Penalties
                                    </span>
                                </div>
                                <div class="dataset-text">
                                    <i class="fa fa-database fa-2x"></i>
                                    <span class="text">
                                        Home Health Agency Utilization and Payment Data 2014
                                    </span>
                                </div>-->
                            </div>
                        </div>
                    </div>
                    <div class="col span_6">
                        <div class="border-heading">
                            <h5 class="blue-heading">Our data in use</h5>
                        </div>
                        <div class="data-images">
                            <div class="images-wrapper">
                                <div class="col span_12">
                                    <div class="col span_6">
                                        <div class="img-outer">
                                            <div class="img-box">
                                                <img src="http://202.47.116.116:8224/wp-content/uploads/market/img-1.jpg" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col span_6">
                                        <div class="img-outer">
                                            <div class="img-box">
                                                <img src="http://202.47.116.116:8224/wp-content/uploads/market/img-1.jpg" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col span_12">
                                    <div class="col span_4">
                                        <div class="img-outer">
                                            <div class="img-box">
                                                <img src="http://202.47.116.116:8224/wp-content/uploads/market/img-1.jpg" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col span_4">
                                        <div class="img-outer">
                                            <div class="img-box">
                                                <img src="http://202.47.116.116:8224/wp-content/uploads/market/img-1.jpg" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col span_4">
                                        <div class="img-outer two-blocks">
                                            <div class="img-box"></div>
                                        </div>
                                        <div class="img-outer two-blocks">
                                            <div class="img-box"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--Latest Datasets-->

    </div>
</section>

</div>
<?php

get_footer();

?>
