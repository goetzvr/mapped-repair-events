<?php

use Cake\Utility\Text;
use App\Controller\Component\StringComponent;

echo $this->element('highlightNavi', [
    'main' => 'HOME'
]);
$tl_location = __('Map Search Location ');
$tl_submit = __('Map Search Button');
$tl_reset = __('Map Button reset');
$tl_search_input_field = __('Map search field preentered');
?>

<div id="workshops-home">

    <?php
        $this->element('addScript', ['script' => 
            JS_NAMESPACE.".Helper.initFindEventsForm('body.workshops.home .find-events-box');
        "]);
        if (!$this->request->getSession()->read('isMobile')) {
            $this->element('addScript', ['script' => "
                var mapObject = new ".JS_NAMESPACE.".Map([], 'search', false, {x:51.4, y:14.7});
                mapObject.map.on('movestart', function() {
            	   mapObject.hideHomeImageOverlay();
                });
            "]);
        }
    ?>

    <?php if (!$this->request->getSession()->read('isMobile')) { ?>
        <div class="left calendar">
        	<?php echo $this->element('calendar'); ?>
        </div>
    <?php } ?>

    <div class="right" style="margin-top: -3px;">

	    <?php if (!$this->request->getSession()->read('isMobile')) { ?>

        	<div class="box filter">
                <?php
                    echo '<span class="wss">' . __('Location') . '</span>';
                    echo $this->Form->control('workshopSearchAddress', [
                        'label' => false,
                        'after' => false,
                        'before' => false,
                        'default' => $tl_search_input_field,
                        'id' => 'workshopSearchAddress'
                    ]);
                ?>
        		<button id="reset" type="button" class="button gray reset"><?php echo __('Clear'); ?></button>
        		<button id="search" type="button" class="button submit"><?php echo __('Search'); ?></button>
        	</div>

        	<div class="sc"></div>
        	<div id="flashMessage"></div>
        	<div class="sc"></div>

        	<div id="mapContainer">
        		<div id="map">
        			<div id="workshopSearchLoader"></div>
        		</div>
        		<img id="mapHomeInfoBox" src="/img/home/willkommen-map.jpg" width="239" height="130" />
        	</div>

        	<br />
        	<div style="float: right; margin: 5px 5px 0px 0px">
        		<a title="Landkarte Repair Cafes" href="/widgets/integration/#2">
        			Diese Karte auf deiner Webseite einfügen
        		</a>
        	</div>

        <?php } ?>

    	<div class="sc"></div>

    	<?php
        	if ($this->request->getSession()->read('isMobile')) {
        	    $this->element('addScript', ['script' => 
        	        JS_NAMESPACE.".MobileFrontend.disableHoverOnSelector('body.workshops.home .find-events-box');
                "]);
        	}
    	?>
    	<div class="find-events-box">
    		<div class="inner">
        		<h3><?php echo __('Find nearby repair events.'); ?></h3>
           		<label><?php echo __('Zip or city'); ?>:</label>
           		<input type="text" />
           		<a class="button violet" href="javascript:void(0);"><?php echo __('Search'); ?></a>
           	</div>
    	</div>

    </div>

    <div id="teaser-buttons-dotted-line-top" class="dotted-line-full-width"></div>

    <?php
        if ($this->request->getSession()->read('isMobile')) {
            $this->element('addScript', ['script' => 
                JS_NAMESPACE.".MobileFrontend.initAdaptTeaserButtonSizeListener('body.workshops.home .teaser-buttons a', 2);
            "]);
        }
    ?>
    <div class="teaser-buttons">
    	<?php
            if ($this->request->getSession()->read('isMobile')) {
        	    $this->element('addScript', ['script' => 
        	        JS_NAMESPACE.".MobileFrontend.disableHoverOnSelector('body.workshops.home .teaser-buttons a');
                "]);
            }
            echo $this->element('home/teaserButtons');
    	?>
    </div>

    <div id="teaser-buttons-dotted-line-bottom" class="dotted-line-full-width"></div>


    <?php
        if ($this->request->getSession()->read('isMobile')) {
            $this->element('addScript', ['script' => 
                JS_NAMESPACE.".MobileFrontend.initAdaptCycle2SlidesWidthListener('body.workshops.home .cycle-slideshow > a');
            "]);
        }
    ?>

    <div class="left">
        <div class="cycle-slideshow"
  			data-cycle-swipe=true
            data-cycle-swipe-fx="scrollHorz"
  			data-cycle-fx="scrollHorz"
            data-cycle-speed="1000"
            data-cycle-timeout="6000"
            data-cycle-slides="> a"
            >
            <?php echo $this->element('home/slides'); ?>
            <div class=cycle-pager></div>
    	</div>
    </div>

    <div class="right">
    	<div class="news-box">
    		<div class="inner">
        		<a class="header" href="/neuigkeiten/"><?php echo __('Latest posts'); ?></a>
        		<?php $i = 0; ?>
        		<?php foreach($latestPosts as $post) { ?>
        			<div class="number"><?php $i++;echo $i;?>.</div>
        			<a class="row" href="<?php echo $this->Html->urlPostDetail($post->url); ?>">
        				<span>
        					<b><?php echo $post->name; ?></b><br />
                        	<?php
                            	$textLength = 290;
                                $postText = StringComponent::prepareTextPreviewForLinkedBoxes($post->text);
                                $postText = StringComponent::makeNoFollow($postText);
                                $postText = StringComponent::cutHtmlString($postText, $textLength);
                                $postText = Text::truncate($postText, $textLength, ['ellipsis' => '... <span class="read-more">weiterlesen</span>']);
                                echo $postText;
        	       			?>
        				</span>
        			</a>
        		<?php } ?>
        	</div>
    	</div>
    </div>
    <div class="sc"></div>

    <?php
        if ($this->request->getSession()->read('isMobile')) {
            $this->element('addScript', ['script' => 
                JS_NAMESPACE.".MobileFrontend.adaptTeaserButtonSize('body.workshops.home .about-us-box a', 1);
            "]);
        }
    ?>

    <?php echo $this->element('home/aboutUsBox'); ?>

    <div class="right">
    	<?php
    	    $this->element('addScript', ['script' =>
    	       JS_NAMESPACE.".Helper.adaptHeightOfWorkshopsBoxLogo('body.workshops.home .workshops-box', '".$this->request->getSession()->read('isMobile')."');
            "]);
	    ?>
        <div class="workshops-box">
       		<a class="header" href="/orte/"><?php echo __('Initiatives from the network'); ?><span><?php echo __('Show all'); ?></span></a>
    		<?php foreach($latestWorkshops as $workshop) { ?>
    			<a class="row" href="<?php echo $this->Html->urlWorkshopDetail($workshop->url); ?>">
					<span class="inner">
    					<img alt="<?php echo h($workshop->name); ?>"
                             src="<?php echo $this->Html->getThumbs100Image($workshop->image, 'workshops'); ?>" />
                        <span class="info">
    	                    <span class="name"><?php echo $workshop->name; ?></span><br />
    						<span class="address"><?php echo $workshop->street . ', ' .$workshop->zip . ' ' . $workshop->city; ?></span>
    					</span>
    				</span>
    			</a>
    		<?php } ?>
        </div>
    </div>

    <div class="sc"></div>

</div>