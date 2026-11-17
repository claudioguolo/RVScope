<?php

use App\Models\AppSettingModel;
use PHPUnit\Framework\TestCase;

final class HostSecuritySettingsTest extends TestCase
{
    public function testLinkIsDisabledByDefault(): void
    {
        $settings = $this->getMockBuilder(AppSettingModel::class)->disableOriginalConstructor()->onlyMethods(['value'])->getMock();
        $settings->expects(self::once())->method('value')->with(AppSettingModel::HOST_SECURITY_LINK_RVTOOLS_KEY, '0')->willReturn('0');
        self::assertFalse($settings->hostSecurityLinkedToRvtools());
    }

    public function testSavedEnabledValueActivatesLink(): void
    {
        $settings = $this->getMockBuilder(AppSettingModel::class)->disableOriginalConstructor()->onlyMethods(['value'])->getMock();
        $settings->expects(self::once())->method('value')->with(AppSettingModel::HOST_SECURITY_LINK_RVTOOLS_KEY, '0')->willReturn('1');
        self::assertTrue($settings->hostSecurityLinkedToRvtools());
    }
}
