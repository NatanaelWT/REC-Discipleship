<?php

if ($action === 'logout') {
    destroy_current_session();
    header('Location: index.php');
    legacy_exit();
}
