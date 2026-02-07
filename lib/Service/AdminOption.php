<?php

namespace OCA\TwoFactorEMail\Service;

enum AdminOption: string
{
    case CODE_LENGTH = 'code_length';
    case CODE_VALID_SECONDS = 'code_valid_seconds';
}
