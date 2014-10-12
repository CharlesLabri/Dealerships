<?php
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');
$body='';
$js='';
//$db = new PDO( 'sqlite:sqlite.db');
$db = new PDO( 'mysql:host=localhost;dbname=DBNAMEHERE','UNAMEHERE','PWORDHERE');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
$db->query('SELECT * FROM timetable limit 1');
}catch(PDOException $ex) {
    if (preg_match('#no such table: timetable|Table.+doesn\'t exist#',$ex->getMessage())){
        $result = $db->query('CREATE TABLE timetable(id int unsigned,date VARCHAR(255) , time VARCHAR(255), advisor VARCHAR(255),name VARCHAR(255),year VARCHAR(255), model VARCHAR(255), description VARCHAR(255), PRIMARY KEY(id) )');
    }
}



if (isset($_GET['upload'])){
    if (isset($_FILES["userfile"])){
        $filename = sys_get_temp_dir().'/csv';
        if ($_FILES["userfile"]["error"] == UPLOAD_ERR_OK) {
            $tmp_name = $_FILES["userfile"]["tmp_name"];
            $name = 'csv';
            move_uploaded_file($tmp_name, $filename);
            $ar = csv_to_array($filename);
            $stmt = $db->prepare("REPLACE INTO timetable(id, date, time, advisor, name, year, model, description) VALUES(?,?,?,?,?,?,?,?)");
            foreach($ar as $row){
                $time = strtotime($row['APPT TM'].' '.$row['APPT DTE']);
                $stmt->execute(array($time,$row['APPT DTE'],$row['APPT TM'],$row['ADVISOR'],$row['NAME'],$row['YR'],$row['CARLINE/MODEL'],$row['VEHICLE DESCRIPTION']));
            }

            $body.= <<<HTML
<div class="alert alert-success" role="alert">Upload successful</div>
HTML;
        }

    }
    $home=substr($_SERVER['REQUEST_URI'],0,strpos($_SERVER['REQUEST_URI'],'?'));
$body.= <<<HTML
<a href="$home">home</a>
<form  role="form" enctype="multipart/form-data" method="post">
<div class="form-group">
    <label for="fileinput">Upload file</label>
    <input id="fileinput" name="userfile" type="file" /><br />
</div>

    <input type="submit" value="Send File" class="btn btn-primary" />
</form>
HTML;


} else {
    $t = time();
    $now = to_table($db->query(sprintf('SELECT * FROM timetable WHERE id>=%u and id<%u ', $t-15*60,$t+16*60)));
    $next = to_table($db->query(sprintf('SELECT * FROM timetable WHERE id>=%u and id<%u ', $t+16*60,$t+45*60)));
    $all = to_table($db->query(sprintf('SELECT * FROM timetable WHERE id>=%u', $t+45*60)));

    $time = date('H:i:s',time());

    $body.= <<< HTML
<h1>Today's Service Appointments<span class="time pull-right label label-primary">$time</span></h1>
<div class="panel panel-success">
  <div class="panel-heading">Current Appointment</div>
  <div class="panel-body">
    $now
  </div>
</div>
<div class="panel panel-info">
  <div class="panel-heading">Next Appointment</div>
  <div class="panel-body">
    $next
  </div>
</div>
<div class="panel panel-default">
  <div class="panel-heading">All Appointments</div>
  <div class="panel-body">
    $all
  </div>
</div>

HTML;
$js=<<<HTML
<script type="text/javascript">
setInterval(function(){
function checkTime(i) {
    if (i<10) {i = "0" + i}  // add zero in front of numbers < 10
    return i;
}
var date = new Date();
var str = date.getHours()+':'+checkTime(date.getMinutes())+':'+checkTime(date.getSeconds());
$('.time').html(str);
},1000);
setInterval(function(){
console.log('reload');
$('.container').load(window.location.href+' .container');
},60000);
</script>
HTML;


}
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
<!-- Optional theme -->
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">
    <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
</head>
<body>
<div class="container">
    <?php echo $body ?>
</div>
<?php echo $js ?>
</body>
</html>

<?php

function to_table($ar){
$result='';
    //<td>${row['date']}</td>
    foreach($ar as $row){
        $result.=<<<HTML
<tr><td>${row['time']}</td><td>${row['advisor']}</td><td>${row['name']}</td><td>${row['year']}</td><td>${row['model']}</td><td>${row['description']}</td></tr>
HTML;
    }
    if (!empty($result))
        //<th>Date</th>
    $result=<<<HTML
<tr><th>Time</th><th>Advisor</th><th>Name</th><th>Year</th><th>Model</th><th>Description</th></tr>
$result
HTML;

    $result = <<<HTML
<table class="table table-condensed table-striped">

$result
</table>
HTML;

    return $result;
}


function csv_to_array($filename='', $delimiter=',')
{
    if(!file_exists($filename) || !is_readable($filename))
        return FALSE;

    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE)
    {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
        {
            if(!$header)
                $header = $row;
            else
                $data[] = array_combine($header, $row);
        }
        fclose($handle);
    }
    return $data;
}