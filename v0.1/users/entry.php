<?php

require 'GET.php';
require 'POST.php';

function indicia_api_users() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    indicia_api_users_post();
  } else {
    indicia_api_users_get();
  }
}