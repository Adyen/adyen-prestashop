#!/bin/bash

code_to_add="if (!defined('_PS_VERSION_')) {
    exit;
}"

./dist/scripts/add_ps_version_check_after_namespace.sh "./adyenofficial" "$code_to_add"
./dist/scripts/add_ps_version_check_after_first_line.sh "./adyenofficial/controllers/admin" "$code_to_add"
./dist/scripts/add_ps_version_check_after_first_line.sh "./adyenofficial/controllers/front" "$code_to_add"
./dist/scripts/add_ps_version_check_after_first_line.sh "./adyenofficial/override" "$code_to_add"
./dist/scripts/add_ps_version_check_after_first_line.sh "./adyenofficial/upgrade" "$code_to_add"
./dist/scripts/add_ps_version_check_after_first_line.sh "./adyenofficial/translations" "$code_to_add"