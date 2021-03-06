<?php
use blog\components\StaticService;
use \common\service\GlobalUrlService;
use \common\components\DataHelper;

StaticService::includeAppCssStatic("/css/market/articles.css", \blog\assets\SuperMarketAsset::className());

?>
<div class="container">
    <div class="row">
        <div class="col-sm-12 col-md-12 col-lg-12">
            <section class="panel panel-default articles-index-main">
                <ul class="list-unstyled articles-index-list">
                    <?php foreach( $data as $_item ):?>
                    <li class="index-article-item ">
                        <a class="article-item-image" href="<?=GlobalUrlService::buildSuperMarketUrl("/default/info",[ "id" => $_item['id'] ]);?>">
                            <img src="<?=$_item['image_url'];?>">
                        </a>
                        <div class="article-item-text">
                            <a class="article-item-title" href="<?=GlobalUrlService::buildSuperMarketUrl("/default/info",[ "id" => $_item['id'] ]);?>"><h1><?=$_item['title'];?></h1></a>
                            <p class="article-item-tags">
                                <a href="<?=GlobalUrlService::buildNullUrl();?>" class="article-tag-element"><?=$_item['type_desc'];?></a>
                            </p>
                            <p class="article-item-desc">
								<?=$_item['content'];?>
                            </p>
                            <p class="article-item-options">
                                <span class="article-item-time">发布于：<span><?=$_item['date'];?></span></span>
                                <a href="<?=GlobalUrlService::buildSuperMarketUrl("/default/info",[ "id" => $_item['id'] ]);?>" class="pull-right article-item-more">阅读全文&gt;&gt;</a>
                            </p>
                        </div>
                    </li>
                    <?php endforeach;?>
                </ul>
            </section>
        </div>
    </div>
</div>