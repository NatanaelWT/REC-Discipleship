<?php

function discipleship_completed_dg3_count(): int {
    // DG 3 is only considered complete after the participant continues to DG 4.
    // The current system has no DG 4 stage yet, so this value stays at 0.
    return 0;
}
