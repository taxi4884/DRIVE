<?php
header("Content-Type: application/json; charset=utf-8");
echo json_encode(["ok"=>true, "service"=>"zeit-api", "time"=>date(DATE_ATOM)]);
