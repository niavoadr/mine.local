<?php
require 'env.php';
$secret = get_radius_mac_secret();
// Si identifiants valides et non expirés : ajouter MAC dans radcheck (value = secret)
// Si expiré : supprimer MAC de radcheck
