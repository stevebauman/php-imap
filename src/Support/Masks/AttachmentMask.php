<?php

namespace Webklex\PHPIMAP\Support\Masks;

use Webklex\PHPIMAP\Attachment;

class AttachmentMask extends Mask
{
    /**
     * @var Attachment
     */
    protected mixed $parent;

    /**
     * Get the attachment content base64 encoded.
     */
    public function getContentBase64Encoded(): ?string
    {
        return base64_encode($this->parent->content);
    }

    /**
     * Get a base64 image src string.
     */
    public function getImageSrc(): ?string
    {
        return 'data:'.$this->parent->content_type.';base64,'.$this->getContentBase64Encoded();
    }
}
