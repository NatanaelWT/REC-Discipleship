<?php

if (is_logged_in() && $page === 'login') {
    redirect_to(branch_home_page(current_user_branch()));
}
