<?php

$page_id = "page_admin";

require_once "private/site.php";

require_once "private/templates/header.php";
require_once "private/templates/navbar.php";

if (!$user->isAdmin() || !$user->isLoggedIn()) {
    ?>
    <div class="container">
        <h1>Invalid access.</h1>
    </div>
    <?php
    include_once "private/templates/footer.php";
    die();
}

$dates = get_variable("dates");
$admins = get_variable("admins");
$arxivs = get_variable("arxivs");
$expire_date = get_variable("expire_date");
$guest_pass = get_variable("guest_pass");

?>
<div class="container">
    <?php
    if ($token->validateToken($params->get("CSRFToken"))) {
        $errors = array();

        $expire_date = $params->getWithDefault("expire_date", "-3 months");
        if (!strtotime($expire_date)) {
            $errors[] = "Invalid expiration date string. Please refer to the <a href='http://php.net/manual/en/datetime.formats.php'>PHP docs</a> for valid time strings.";
        }

        $guest_pass = $params->getWithDefault(
            "guest_pass",
            "GuestCoffeePassword"
        );

        $arxivs = $params->getWithDefault(
            "arxivs",
            "astro-ph.CO, astro-ph.HE, astro-ph.GA, astro-ph.IM, gr-qc, hep-ph, hep-th"
        );
        $arxivs = array_map("trim", explode(",", $arxivs));
        foreach ($arxivs as $arxiv) {
            // make sure page is returning XML... XMLReader's isValid() seems to
            // consider the plain text returned on failure as valid,
            // so just check for the xml tag at the beginning.
            if ($feed_reply = file_get_contents(ARXIV_RSS_BASE_URL . $arxiv)) {
                if (substr($feed_reply, 0, 5) != '<?xml') {
                    $errors[] = "Unable to read from arxiv: `" . o($arxiv) . "`! Reply is:"
                    . "<pre>" . o($feed_reply) . "</pre>";
                }
            } else {
                $errors[] = "Unable to read from arxiv: `" . o($arxiv) . "`!";
            }
        }

        $dates = json_decode($params->get("dates"));
        $sorted = usort($dates, "date_sort"); // sorted is bool
        if ($dates === false || !$sorted) {
            $errors[] = "Error making changes to dates: you must set a date.";
        }

        if ($errors) {
            print_errors($errors);
        } else {
            set_variable("dates", $dates);
            set_variable(
                "admins",
                $params->getWithDefault(
                    "admins",
                    $user->id()
                )
            );
            set_variable("arxivs", $arxivs);
            set_variable("expire_date", $expire_date);
            if($guest_pass!="") {
                set_variable("guest_pass", $guest_pass);
            } else {
                set_variable("guest_pass", "GuestCoffeePassword");
            }

            $dates = get_variable("dates");
            $admins = get_variable("admins");
            $arxivs = get_variable("arxivs");
            $expire_date = get_variable("expire_date");
            $guest_pass = get_variable("guest_pass");
            print_alert("Changes successfully made.", "success");
        }
    }

    if (!get_variable("admins")) {
        print_alert("Warning: No administrators are defined yet, so everyone has access to this page.", "danger");
    }
    ?>

    <h1>Coffee Discussion Settings</h1>
    <h2>Settings</h2>
    <form method="POST">
        <div class="list-group" id="admin_date_selectors">
            <div class="list-group-item list-group-item-info">
                <a role='button' href="#" class='pull-right add-meeting' id='meeting_add'>
                    <span class='glyphicon glyphicon-plus'></span>
                </a>
                <h4 style="margin: 0;">
                    Set meeting dates
                </h4>
            </div>
        </div>
        <div class="form-group">
            <label for="admin_ids">Comma-Separated list of CAS IDs of Administrators</label>
            <input type="text" class="form-control" id="admin_ids" name="admins" value="<?php print o($admins); ?>" placeholder="Enter a comma-separated list of admin IDs.">
        </div>
        <div class="form-group">
            <label for="arxivs">Comma-Separated list of arXivs to import</label>
            <input type="text" class="form-control" id="arxivs" name="arxivs" value="<?php print o(implode(",", $arxivs)); ?>" placeholder="Eg., 'astro-ph.CO'">
        </div>
        <div class="form-group">
            <label for="expire_date">Date after which to remove old papers with no votes from the system. Can be any string <a href='https://php.net/manual/en/datetime.formats.php'>readable</a> by PHP's strtotime() function.</label>
            <input type="text" class="form-control" id="expire_date" name="expire_date" value="<?php print o($expire_date); ?>" placeholder="Eg., '-3 months'">
        </div>
        <div class="form-group">
            <label for="arxivs">Guest user password</label>
            <input type="text" class="form-control" id="guestpass" name="guest_pass" value="<?php print o($guest_pass); ?>" placeholder="Eg., 'password'">
        </div>

        <input type="hidden" id="admin_date_selectors_dates" name="dates" value="<?php print o(json_encode($dates)); ?>">
        <input type="hidden" name="CSRFToken" value="<?php print $token->getToken(); ?>">
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
    <h2>Administrative Tasks</h2>
    <p>
        <?php
            $num_papers = $coffee_conn->dbQuery("SELECT count(*) as num FROM papers");
            $num_votes = $coffee_conn->dbQuery("SELECT count(*) as num FROM votes");
            $num_users = $coffee_conn->dbQuery("SELECT count(DISTINCT userId) as num FROM votes");
        ?>
        Currently storing <strong><?php print $num_papers[0]->num; ?></strong> paper abstracts.
        Tracking <strong><?php print $num_votes[0]->num; ?></strong> votes from
        <strong><?php print $num_users[0]->num; ?></strong> users.
    </p>
    <form method="POST" action="cron">
        <button type="submit" class="btn btn-default">
            <span class="glyphicon glyphicon-import" aria-hidden="true"></span>
            Import &amp; Expire Papers
        </button>
    </form>
    <h2>Error Logs</h2>
    <p>
        <?php
        if (file_exists(PHP_LOG_FILE)) {
            // file must be writable if it exists
            if (!is_writable(PHP_LOG_FILE)) {
                print_alert("Log file is not writable: `" . PHP_LOG_FILE . "`. For logs to appear here it must be writable.", "danger");
            }
        } else {
            // parent dir must be writable if not
            $parent_dir = dirname(PHP_LOG_FILE);
            if (!is_writable($parent_dir)) {
                print_alert("Log file directory is not writable: `" . $parent_dir . "`. For logs to appear here it must be writable.", "danger");
            }
        }

        if (file_exists(PHP_LOG_FILE) && $log = trim(file_get_contents(PHP_LOG_FILE))) {
            print "<div class='form-group'>";
            print "<label for='errors'>Error Log Content</label>";
            print "<textarea class='form-control' rows='10' readonly id='errors'>";
            print o($log);
            print "</textarea>";
            print "</div>";
        } else {
            print "Currently there are no errors in error.log.";
        }
        ?>
    </p>
</div>

<?php
require_once "private/templates/footer.php";
