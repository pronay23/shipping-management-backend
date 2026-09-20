<?php
$user = App\Models\Employee::where('name', 'pronay')->first();
if ($user) {
    $user->assignRole('super-admin');
    echo "Role assigned successfully.\n";
} else {
    echo "User not found.\n";
}
