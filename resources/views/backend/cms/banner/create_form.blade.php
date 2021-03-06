@include('backend.master.form.fields.text', [
    'name' => 'name',
    'label' => 'Name',
    'key' => 'name',
    'attr' => [
        'class' => 'form-control',
        'id' => 'name'
    ],
    'required' => TRUE
])

@include('backend.master.form.fields.select', [
    'name' => 'banner_group_id',
    'label' => 'Banner Group',
    'key' => 'banner_group_id',
    'options' => $bannerGroupOptions,
    'attr' => [
        'class' => 'form-control select2',
        'id' => 'banner_group_id'
    ],
    'defaultOptions' => old('banner_group_id', $bannerGroup->id)
])

@include('backend.master.form.fields.textarea', [
    'name' => 'body',
    'label' => 'Content',
    'key' => 'body',
    'attr' => [
        'class' => 'form-control wysiwyg-editor',
        'id' => 'description'
    ]
])

@include('backend.master.form.fields.images', [
    'name' => 'images',
    'label' => 'Banner Image',
    'key' => 'images',
    'attr' => [
        'class' => 'form-control',
        'id' => 'images'
    ],
    'multiple' => TRUE,
    'limit' => 2,
    'existing' => $banner->images
])

@include('backend.master.form.fields.videos', [
    'name' => 'videos',
    'label' => 'Banner Video',
    'key' => 'videos',
    'attr' => [
        'class' => 'form-control',
        'id' => 'videos'
    ],
    'multiple' => TRUE,
    'limit' => 3,
    'existing' => $banner->videos
])

@include('backend.master.form.fields.checkbox', [
    'name' => 'active',
    'label' => 'Active',
    'key' => 'active',
    'value' => 1,
    'checked' => $banner->exists?$banner->active:true,
    'attr' => [
        'class' => 'make-switch',
        'id' => 'active',
        'data-on-color' => 'warning'
    ],
    'appends' => '<a class="btn btn-default" href="#active-schedule-modal" data-toggle="modal"><i class="fa fa-calendar"></i></a>'
])

<div id="active-schedule-modal" class="modal fade" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                <h4 class="modal-title">Active Schedule</h4>
            </div>
            <div class="modal-body">
                @include('backend.master.form.fields.datetime', [
                    'name' => 'active_date_from',
                    'label' => 'Active From',
                    'key' => 'active_date_from',
                    'attr' => [
                        'id' => 'active_date_from'
                    ],
                ])

                @include('backend.master.form.fields.datetime', [
                    'name' => 'active_date_to',
                    'label' => 'Active Until',
                    'key' => 'active_date_to',
                    'attr' => [
                        'id' => 'active_date_to'
                    ],
                ])
            </div>
            <div class="modal-footer">
                <button class="btn green" data-dismiss="modal" aria-hidden="true">Done</button>
            </div>
        </div>
    </div>
</div>

<hr/>

@include('backend.master.form.fields.text', [
    'name' => 'data[call_to_action]',
    'label' => 'Call to Action',
    'key' => 'data.call_to_action',
    'attr' => [
        'class' => 'form-control',
        'id' => 'data[call_to_action]'
    ],
    'defaultValue' => old('data.call_to_action', $banner->exists?$banner->getData('call_to_action'):null)
])

@include('backend.master.form.fields.text', [
    'name' => 'data[url]',
    'label' => 'Path',
    'key' => 'data.url',
    'attr' => [
        'class' => 'form-control',
        'id' => 'data[url]'
    ],
    'defaultValue' => old('data.url', $banner->exists?$banner->getData('url'):null)
])

@include('backend.master.form.fields.select', [
    'name' => 'data[target]',
    'label' => 'Open path in',
    'key' => 'data.target',
    'options' => \Kommercio\Models\CMS\MenuItem::getLinkTargetOptions(),
    'attr' => [
        'class' => 'form-control select2',
        'id' => 'data[target]'
    ],
    'defaultOptions' => old('data.target', $banner->exists?$banner->getData('target'):null)
])

@include('backend.master.form.fields.text', [
    'name' => 'data[class]',
    'label' => 'Class',
    'key' => 'data.class',
    'attr' => [
        'class' => 'form-control',
        'id' => 'data[class]'
    ],
    'defaultValue' => old('data.class', $banner->exists?$banner->getData('class'):null)
])