<?php

$page_id = "page_home";

require_once("private/site.php");

require_once("private/templates/header.php");
require_once("private/templates/navbar.php");
require_once("private/templates/jumbotron.php");

?>
<script type="text/javascript" src="<?php print path(); ?>js/sidebar.js"></script>
<div class="container">
  
  <div class="row">
    <div class="panel panel-default">
      <div class="panel-body">
        <input id="arxiv_search" class="form-control" placeholder="Search...">
        <div id="arxiv_search_results"></div>
      </div>
    </div>
  </div>

  <div class="row">
    
    <div class="col-sm-3" id="leftCol">
      <div class="btn-group-vertical toggle-content" id="arxiv-toggle-list" role="group" data-clampedwidth="#leftCol">
        <a class="list-group-item active">
          <h5 class="list-group-item-heading"> Display arXiv results from:</h5>
        </a>
        <?php require_once("private/templates/calendar.php"); ?>
      </div>
    </div>

    <div class="col-sm-9">
      <?php require_once("private/templates/feed.php"); ?>
    </div>

  </div>
</div>

<?php
require_once("private/templates/footer.php");
