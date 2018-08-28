<?php

switch($_SERVER['REQUEST_METHOD'])
{
  case 'GET': echo "Hello you used GET!!";
    break;
  case 'POST': echo "Hello you used POST!!";
    break;
  default:
}

?>
