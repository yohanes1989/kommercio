<?php
$newMenu = $menu;
$newDepth = $depth;
$count += 1;
$featureEnabled = isset($newMenu['feature'])?ProjectHelper::isFeatureEnabled($newMenu['feature']):true;
$childPermissions = isset($newMenu['children'])?array_pluck($newMenu['children'], 'permissions'):[];
?>
@if((isset($newMenu['permissions']) && Gate::allows('access', [$newMenu['permissions']]) && $featureEnabled) || ($childPermissions && Gate::allows('access', [$childPermissions])) || (!isset($newMenu['permissions']) && !isset($newMenu['children'])))
<li class="nav-item {{ $count==0?'start':'' }} {{ $newDepth == 0?'open':'' }} {{ isset($newMenu['active_path'])?(NavigationHelper::activeClass($newMenu['active_path'])?'active':''):'' }}">
    @if(isset($newMenu['children']))
        <a href="javascript:;" class="nav-link nav-toggle">
            {!! isset($newMenu['prepend'])?$newMenu['prepend']:'' !!}
            <span class="title">{{ $newMenu['name'] }}</span>
            <span class="arrow open"></span>
        </a>

        <ul class="sub-menu" style="{{ $newDepth == 0?'display: block;':'' }}">
        @foreach($newMenu['children'] as $menuChildName => $menuChild)
            @include('backend.master.menu_item', ['menu' => $menuChild, 'depth' => $newDepth + 1])
        @endforeach
        </ul>
    @else
        <a href="{{ isset($newMenu['route'])?route($newMenu['route'], (isset($newMenu['route_params'])?$newMenu['route_params']:[])):(isset($newMenu['path'])?$newMenu['path']:'') }}" class="nav-link ">
            {!! isset($newMenu['prepend'])?$newMenu['prepend']:'' !!} <span class="title">{{ $newMenu['name'] }}</span>
        </a>
    @endif
</li>
@endif