<?php
        $email;$comment;$captcha;
        if(isset($_POST['email'])){
          $email=$_POST['email'];
        }
        if(isset($_POST['comment'])){
          $comment=$_POST['comment'];
        }
        if(isset($_POST['g-recaptcha-response'])){
          $captcha=$_POST['g-recaptcha-response'];
        }
        if(!$captcha){
          echo '<h2>Please check the the captcha form.</h2>';
          exit;
        }

       
        $secretKey = "6Lea68UUAAAAABoeM2ZN5_vn7yCHtEMCtW5tcn8y";
        $ip = $_SERVER['REMOTE_ADDR'];
        // post request to server
        $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secretKey) .  '&response=' . urlencode($captcha);
        $response = file_get_contents($url);
        $responseKeys = json_decode($response,true);
        // should return JSON with success as true
        if($responseKeys["success"]) {
                echo '<h2>Thanks for posting comment</h2>';
        } else {
                echo '<h2>You are spammer ! Get the @$%K out</h2>';
        }
?>