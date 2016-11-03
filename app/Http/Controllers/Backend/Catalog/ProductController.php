<?php

namespace Kommercio\Http\Controllers\Backend\Catalog;

use Carbon\Carbon;
use Collective\Html\FormFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\Session;
use Kommercio\Facades\CurrencyHelper;
use Kommercio\Facades\FrontendHelper;
use Kommercio\Facades\ProjectHelper;
use Kommercio\Http\Controllers\Controller;
use Kommercio\Http\Requests\Backend\Catalog\ProductFormRequest;
use Kommercio\Http\Requests\Backend\Catalog\ProductVariationFormRequest;
use Kommercio\Models\Order\LineItem;
use Kommercio\Models\Order\Order;
use Kommercio\Models\Order\OrderLimit;
use Kommercio\Models\Product;
use Kommercio\Models\ProductAttribute\ProductAttribute;
use Kommercio\Models\ProductAttribute\ProductAttributeValue;
use Kommercio\Models\ProductCategory;
use Kommercio\Models\ProductComposite\ProductComposite;
use Kommercio\Models\ProductComposite\ProductCompositeConfiguration;
use Kommercio\Models\ProductDetail;
use Kommercio\Models\ProductFeature\ProductFeature;
use Kommercio\Models\ProductFeature\ProductFeatureValue;
use Kommercio\Facades\PriceFormatter;

class ProductController extends Controller{
    public function index(Request $request)
    {
        $qb = Product::productEntity();

        if($request->ajax() || $request->wantsJson()){
            $totalRecords = $qb->count();

            //Join Translation and Detail
            $qb->with('defaultCategory', 'manufacturer')->joinTranslation()->joinDetail()->selectSelf();

            foreach($request->input('filter', []) as $searchKey=>$search){
                if(trim($search) != ''){
                    if($searchKey == 'category_name'){
                        $qb->whereHas('categories', function($query) use ($search){
                            $query->whereTranslationLike('name', '%'.$search.'%');
                        });
                    }elseif($searchKey == 'manufacturer') {
                        $qb->whereHas('manufacturer', function($query) use ($search){
                            $query->where('name', 'LIKE', '%'.$search.'%');
                        });
                    }else{
                        $qb->where($searchKey, 'LIKE', '%'.$search.'%');
                    }
                }
            }

            $filteredRecords = $qb->count();

            $columns = $request->input('columns');
            foreach($request->input('order', []) as $order){
                $orderColumn = $columns[$order['column']];

                $qb->orderBy($orderColumn['name'], $order['dir']);
            }

            $qb->orderBy('products.created_at', 'DESC');

            if($request->has('length')){
                $qb->take($request->input('length'));
            }

            if($request->has('start') && $request->input('start') > 0){
                $qb->skip($request->input('start'));
            }

            $products = $qb->get();

            $meat = $this->prepareDatatables($products, $request->input('start'));

            $data = [
                'draw' => $request->input('draw'),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $meat
            ];

            return response()->json($data);
        }

        return view('backend.catalog.product.index');
    }

    protected function prepareDatatables($products, $orderingStart=0)
    {
        $meat= [];

        foreach($products as $idx=>$product){
            $productAction = FormFacade::open(['route' => ['backend.catalog.product.delete', 'id' => $product->id]]);
            $productAction .= '<div class="btn-group btn-group-sm">';

            if(Gate::allows('access', ['edit_product'])):
            $productAction .= '<a class="btn btn-default" href="'.route('backend.catalog.product.edit', ['id' => $product->id, 'backUrl' => RequestFacade::fullUrl()]).'"><i class="fa fa-pencil"></i> Edit</a>';
            endif;

            if(Gate::allows('access', ['delete_product'])):
            $productAction .= '<button class="btn btn-default" data-toggle="confirmation" data-original-title="Are you sure?" title=""><i class="fa fa-trash-o"></i> Delete</button>';
            endif;

            if(Gate::allows('access', ['create_product'])):
                $productAction .= '<a class="btn btn-default" href="'.route('backend.catalog.product.create', ['clone' => $product->id, 'backUrl' => RequestFacade::fullUrl()]).'"><i class="fa fa-clone"></i> Clone</a>';
            endif;

            $productAction .= '</div>'.FormFacade::close();

            $meat[] = [
                $idx + 1 + $orderingStart,
                $product->hasThumbnail()?'<img class="img-responsive" src="'.asset($product->thumbnail->getImagePath('backend_thumbnail')).'" />':'',
                $product->name.($product->productDetail->new?' <span class="label label-sm label-success circle">New</span>':''),
                $product->sku,
                $product->defaultCategory?$product->defaultCategory->name:'',
                $product->manufacturer?$product->manufacturer->name:'',
                PriceFormatter::formatNumber($product->getRetailPrice()),
                PriceFormatter::formatNumber($product->getNetPrice()),
                '<i class="fa fa-'.(isset($product->productDetail) && $product->productDetail->active?'check text-success':'remove text-danger').'"></i>',
                $product->created_at->format('d M Y H:i'),
                $productAction
            ];
        }

        return $meat;
    }

    public function create(Request $request)
    {
        if($request->has('clone') && !$request->old()){
            $referencedProduct = Product::findOrFail($request->get('clone'));
            $product = $referencedProduct->replicate();

            $product->setRelation('translations', $referencedProduct->translations);
            $product->setRelation('defaultCategory', $referencedProduct->defaultCategory);
            $product->setRelation('categories', $referencedProduct->categories);

            $product->productDetail = $referencedProduct->productDetail;
        }else{
            $product = new Product();
            $product->productDetail = new ProductDetail([
                'active' => TRUE,
                'available' => TRUE
            ]);
        }

        $currencyOptions = CurrencyHelper::getCurrencyOptions();

        return view('backend.catalog.product.create', [
            'product' => $product,
            'currencyOptions' => $currencyOptions
        ]);
    }

    public function store(ProductFormRequest $request)
    {
        $product = new Product($request->all());
        $product->combination_type = $request->input('combination_type');
        if($request->has('default_category')){
            $category = ProductCategory::findOrFail($request->input('default_category'));
            $product->defaultCategory()->associate($category);
        }

        $product->save();

        $product->categories()->sync($request->input('categories', []));

        $images = [];
        foreach($request->input('images', []) as $idx=>$image){
            $images[$image] = [
                'type' => 'image',
                'caption' => $request->input('images_caption.'.$idx, null),
                'locale' => $product->getTranslation()->locale
            ];
        }
        $product->getTranslation()->syncMedia($images, 'image');

        $thumbnails = [];
        foreach($request->input('thumbnails', []) as $idx=>$image){
            $thumbnails[$image] = [
                'type' => 'thumbnail',
                'caption' => $request->input('thumbnails_caption.'.$idx, null),
                'locale' => $product->getTranslation()->locale
            ];
        }
        $product->getTranslation()->syncMedia($thumbnails, 'thumbnail');

        $productDetail = new ProductDetail();
        $productDetail->fill($request->input('productDetail'));
        $productDetail->store_id = $request->input('store_id');
        $productDetail->product()->associate($product);
        $productDetail->save();

        $message = 'New product '.$product->name.' is successfully created.';

        //Cross Sell
        foreach($request->input('cross_sell_product', []) as $idx=>$crossSellProduct){
            $product->crossSellTo()->attach($crossSellProduct, ['type' => 'cross_sell', 'sort_order' => $idx]);
        }

        if($request->get('action') == 'save_stay'){
            return redirect()->route('backend.catalog.product.edit', ['id' => $product->id])->with('success', [$message]);
        }else{
            return redirect($request->get('backUrl', route('backend.catalog.product.index')))->with('success', [$message]);
        }
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);

        $oldFeatures = array_keys(old('features', []));

        $features = [];
        if($oldFeatures){
            foreach($oldFeatures as $oldFeature){
                $features[] = ProductFeature::findOrFail($oldFeature);
            }
        }else{
            $features = $product->productFeatures;
            $oldFeatures = $features->pluck('id')->all();
        }

        $allFeatures = ProductFeature::withTranslation()->get();

        $featureOptions = [];
        foreach($allFeatures as $allFeature){
            if(!in_array($allFeature->id, $oldFeatures)){
                $featureOptions[$allFeature->id] = $allFeature->name;
            }
        }

        $currencyOptions = CurrencyHelper::getCurrencyOptions();

        return view('backend.catalog.product.edit', [
            'product' => $product,
            'featureOptions' => $featureOptions,
            'features' => $features,
            'currencyOptions' => $currencyOptions
        ]);
    }

    public function update(ProductFormRequest $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->fill($request->all());

        if($request->has('default_category')){
            $category = ProductCategory::findOrFail($request->input('default_category'));
            $product->defaultCategory()->associate($category);
        }

        $product->save();

        $product->categories()->sync($request->input('categories', []));

        $images = [];
        foreach($request->input('images', []) as $idx=>$image){
            $images[$image] = [
                'type' => 'image',
                'caption' => $request->input('images_caption.'.$idx, null),
                'locale' => $product->getTranslation()->locale
            ];
        }
        $product->getTranslation()->syncMedia($images, 'image');

        $thumbnails = [];
        foreach($request->input('thumbnails', []) as $idx=>$image){
            $thumbnails[$image] = [
                'type' => 'thumbnail',
                'caption' => $request->input('thumbnails_caption.'.$idx, null),
                'locale' => $product->getTranslation()->locale
            ];
        }
        $product->getTranslation()->syncMedia($thumbnails, 'thumbnail');

        $productDetail = $product->getStoreProductDetailOrNew($request->input('store_id'));

        $productDetail->fill($request->input('productDetail'));

        $productDetail->save();

        //Update Product Features and save custom feature value
        $syncFeatures = [];
        foreach($request->input('features', []) as $featureId=>$featureValue){
            if($request->has('features_custom.'.$featureId)){
                $newFeatureValue = ProductFeatureValue::whereTranslation('name', $request->input('features_custom.'.$featureId))->first();

                if(!$newFeatureValue){
                    $newFeatureValue = new ProductFeatureValue();
                    $newFeatureValue->fill([
                        'name' => $request->input('features_custom.'.$featureId),
                        'custom' => TRUE,
                    ]);

                    $newFeatureValue->productFeature()->associate($featureId);
                    $newFeatureValue->save();
                }

                $featureValue = $newFeatureValue->id;
            }

            if(!empty($featureValue)){
                $syncFeatures[$featureValue] = [
                    'product_feature_id' => $featureId
                ];
            }
        }
        $product->productFeatureValues()->sync($syncFeatures);

        //Composite Configurations
        $count = 0;
        $existingComposites = $product->composites->pluck('id', 'id')->all();

        foreach($request->input('compositeConfigurations', []) as $idx => $compositeConfigurationData){
            $compositeConfiguration = null;
            $updatePivotFlag = false;

            $compositeConfiguration = ProductComposite::where('name', $compositeConfigurationData['name'])->first();

            if($compositeConfiguration && isset($existingComposites[$compositeConfiguration->id])){
                unset($existingComposites[$compositeConfiguration->id]);
                $updatePivotFlag = true;
            }

            if(!$compositeConfiguration){
                $compositeConfiguration = new ProductComposite();
            }

            $compositeConfiguration->fill($compositeConfigurationData);
            $compositeConfiguration->save();

            $pivotData = [
                'minimum' => $compositeConfigurationData['minimum'],
                'maximum' => $compositeConfigurationData['maximum'],
                'sort_order' => $count
            ];

            if($updatePivotFlag){
                $product->composites()->updateExistingPivot($compositeConfiguration->id, $pivotData);
            }else{
                $product->composites()->attach($compositeConfiguration, $pivotData);
            }

            $configuredProductsData = [];

            $productCompositeConfiguration = $product->composites()->where('product_composite_id', $compositeConfiguration->id)->first()->pivot;

            $productCompositeConfiguration->configuredProducts()->detach();
            foreach($request->input('composite_products_'.$idx.'_product') as $idy => $configuredProduct){
                $configuredProductsData[$configuredProduct] = [
                    'sort_order' => $idy
                ];
            }

            $productCompositeConfiguration->configuredProducts()->sync($configuredProductsData);

            $count += 1;
        }

        //Remove unused composites
        foreach($existingComposites as $existingComposite){
            $existingComposite = ProductComposite::find($existingComposite);

            $checkIfCompositeIsUsed = $existingComposite->products()->where('products.id', '<>', $product->id)->count() > 0;
            if($checkIfCompositeIsUsed){
                $product->composites()->detach($existingComposite);
            }else{
                $existingComposite->delete();
            }
        }

        //Cross Sell
        $product->crossSellTo()->detach();
        foreach($request->input('cross_sell_product', []) as $idx=>$crossSellProduct){
            $product->crossSellTo()->attach($crossSellProduct, ['type' => 'cross_sell', 'sort_order' => $idx]);
        }

        //Inventory
        if($product->variations->count() > 0){
            foreach($product->variations as $variation){
                $variation->productDetail->manage_stock = $request->input('variation.'.$variation->id.'.productDetail.manage_stock', false);
                $variation->productDetail->taxable = $productDetail->taxable;
                $variation->productDetail->save();

                //Update children product categories
                $variation->categories()->sync($request->input('categories', []));

                //Update children features
                $variation->productFeatureValues()->sync($syncFeatures);

                //Update manufacturer
                $variation->manufacturer_id = $request->input('manufacturer_id', null);

                if($variation->productDetail->manage_stock){
                    $variation->saveStock($request->input('variation.'.$variation->id.'.stock'), $request->input('warehouse_id'));
                }

                $variation->save();
            }
        }else{
            if($productDetail->manage_stock){
                $product->saveStock($request->input('stock'), $request->input('warehouse_id'));
            }
        }

        $message = $product->name.' has successfully been updated.';

        if($request->get('action') == 'save_stay'){
            return redirect()->route('backend.catalog.product.edit', ['id' => $product->id])->with('success', [$message]);
        }else{
            return redirect($request->get('backUrl', route('backend.catalog.product.index')))->with('success', [$message]);
        }
    }

    public function delete(Request $request, $id)
    {
        $product = Product::withTrashed()->findOrFail($id);

        if(!$this->deleteable($product->id)){
            return redirect()->back()->withErrors(['Can\'t delete this product. It is used in settled Orders.']);
        }

        $name = $product->name;

        //Remove all media first. We do it manually because Translation model is cascaded, so we can't do this on Translation delete
        foreach($product->translations as $translation){
            $translation->deleteMedia('image');
            $translation->deleteMedia('thumbnail');
        }

        LineItem::isProduct($product->id)->delete();

        $product->forceDelete();

        if($request->ajax()){
            return response()->json([
                'result' => 'success',
                'message' => $name.' has been deleted.',
                '_token' => csrf_token()
            ]);
        }else{
            return redirect()->back()->with('success', [$name.' has been deleted.']);
        }
    }

    public function variationIndex($id)
    {
        $parentProduct = Product::findOrFail($id);

        $return = view('backend.catalog.product.product_variation_index', [
            'variations' => $parentProduct->variations,
            'product' => $parentProduct
        ])->render();

        return response()->json([
            'html' => $return,
            '_token' => csrf_token()
        ]);
    }

    public function variationForm(Request $request, $id, $variation_id=null)
    {
        $product = Product::findOrFail($id);
        $variation = null;

        if($variation_id){
            $variation = Product::with('productAttributes')->findOrFail($variation_id);

            $variationAttributes = $variation->getProductAttributeWithValues();
        }

        $existingAttributes = [];

        $notIns = $request->input('variation.attributes', isset($variationAttributes)?$variationAttributes:[]);

        if($request->has('variation.remove_attribute') && isset($notIns[$request->input('variation.remove_attribute')])){
            unset($notIns[$request->input('variation.remove_attribute')]);
        }

        $notIns = array_keys($notIns);

        if($request->has('variation.new_attribute') && !$request->has('variation.remove_attribute')){
            $notIns += $request->input('variation.new_attribute', []);
        }

        foreach($notIns as $notIn){
            $existingAttribute = ProductAttribute::findOrFail($notIn);
            $existingAttributes[$existingAttribute->id] = [
                'name' => $existingAttribute->name,
                'options' => $existingAttribute->values->pluck('name', 'id')->all()
            ];
        }

        $attributeOptions = [
            '' => 'Select attribute to add'
        ];

        $attributeQb = ProductAttribute::withTranslation()->orderBy('sort_order', 'ASC');

        if(!empty($notIns)){
            $attributeQb->whereNotIn('id', $notIns);
        }

        $attributes = $attributeQb->get();
        foreach($attributes as $attribute){
            if($attribute->values->count() > 0){
                $attributeOptions[$attribute->id] = $attribute->name;
            }
        }

        if($request->has('variation')){
            $oldValues = $request->all();
        }else{
            if($variation){
                $oldValues['variation'] = $variation->toArray();
                $oldValues['variation']['productDetail'] = $variation->productDetail->toArray();
                $oldValues['variation']['attributes'] = $variation->getProductAttributeWithValues();
            }else{
                $oldValues['variation'] = [
                    'sku' => $product->sku,
                    'productDetail' => [
                        //'retail_price' => $product->productDetail->retail_price,
                        'active' => true,
                        'available' => true,
                    ]
                ];
            }
        }

        Session::flashInput($oldValues);

        $form = view('backend.catalog.product.product_variation_form', [
            'product' => $product,
            'variation' => $variation,
            'attributeOptions' => $attributeOptions,
            'existingAttributes' => $existingAttributes
        ])->render();

        return response()->json([
            'html' => $form,
            '_token' => csrf_token()
        ]);
    }

    public function variationSave(ProductVariationFormRequest $request, $id, $variation_id=null)
    {
        $parentProduct = Product::findOrFail($id);

        $new = FALSE;
        $product = null;

        if($variation_id){
            $product = Product::findOrFail($variation_id);
        }

        if(!$product){
            $product = new Product();
            $product->combination_type = Product::COMBINATION_TYPE_VARIATION;

            $new = TRUE;
        }

        $product->fill($request->input('variation'));
        $product->name = $parentProduct->name;

        $loadedAttributeValues = [];
        $toSyncAttributeValues = [];

        foreach($request->input('variation.attributes', []) as $attributeId => $attributeValue){
            $loadedAttributeValues[$attributeId] = ProductAttributeValue::findOrFail($attributeValue);
            $product->name .= ' '.$loadedAttributeValues[$attributeId]->name;

            $toSyncAttributeValues[$attributeValue] = [
                'product_attribute_id' => $attributeId
            ];
        }

        $product->parent()->associate($parentProduct);

        //Update manufacturer
        $product->manufacturer_id = $parentProduct->manufacturer_id;

        $product->save();

        //Update children product categories
        $product->categories()->sync($parentProduct->categories->pluck('id')->all());

        //Update children features
        $features = [];

        foreach($parentProduct->productFeatureValues as $parentProductFeature){
            $features[$parentProductFeature->id] = [
                'product_feature_id' => $parentProductFeature->pivot->product_feature_id
            ];
        }

        $product->productFeatureValues()->sync($features);

        $product->productAttributeValues()->sync($toSyncAttributeValues);

        $images = [];

        if($request->has('variation.images')){
            foreach($request->input('variation.images', []) as $idx=>$image){
                $images[$image] = [
                    'type' => 'image',
                    'locale' => $product->getTranslation()->locale
                ];
            }
        }

        $product->getTranslation()->syncMedia($images, 'image');

        $thumbnails = [];

        if($request->has('variation.thumbnails')){
            foreach($request->input('variation.thumbnails', []) as $idx=>$image){
                $thumbnails[$image] = [
                    'type' => 'thumbnail',
                    'locale' => $product->getTranslation()->locale
                ];
            }
        }

        $product->getTranslation()->syncMedia($thumbnails, 'thumbnail');

        $productDetail = $product->productDetail;

        if(!$productDetail->exists){
            $productDetail->product()->associate($product);
        }

        $productDetail->fill($request->input('variation.productDetail'));
        $productDetail->store_id = $request->input('variation.store_id');
        $productDetail->taxable = $parentProduct->productDetail->taxable;
        $productDetail->save();

        if($new){
            $message = $product->name.' is successfully created.';
        }else{
            $message = $product->name.' is successfully updated.';
        }

        return response()->json([
            'message' => $message,
            '_token' => csrf_token(),
            'result' => 'success'
        ]);
    }

    public function variationBulkForm($id)
    {
        $product = Product::findOrFail($id);

        $attributes = ProductAttribute::orderBy('sort_order', 'ASC')->get();

        $form = view('backend.catalog.product.product_variation_bulk_form', [
            'attributes' => $attributes,
            'product' => $product,
        ])->render();

        return response()->json([
            'html' => $form,
            '_token' => csrf_token()
        ]);
    }

    public function variationBulkSave(Request $request, $id)
    {
        $this->validate($request, [
            '_bulkAttribute' => 'required|array'
        ]);

        $requestAttributes = $request->all();
        if(!$request->has('variation.productDetail.retail_price')){
            $requestAttributes['variation']['productDetail']['retail_price'] = null;
        }

        $request->replace($requestAttributes);

        $attributesData = $request->input('_bulkAttribute');

        $parentProduct = Product::findOrFail($id);

        $combinations = $this->getCombinationsFromArray($attributesData);

        foreach($attributesData as $attributeId => $attributesValues){
            $attributes[] = $attributeId;
        }

        foreach($combinations as $combination){
            if(count($combination) == count($attributesData)){
                //Check if variation exists
                $attributeValues = [];

                foreach($combination as $attributeValue){
                    $attributeValues[$this->_searchAttributeId($attributesData, $attributeValue)] = $attributeValue;
                }

                $existingVariations = $parentProduct->getVariationsByAttributes($attributes, $attributeValues);

                if($existingVariations->count() < 1){
                    $product = new Product();
                    $product->combination_type = Product::COMBINATION_TYPE_VARIATION;

                    $product->name = $parentProduct->name;
                    $product->sku = $parentProduct->sku;

                    $loadedAttributeValues = [];
                    $toSyncAttributeValues = [];

                    foreach($combination as $attributeValue){
                        $loadedAttributeValue = ProductAttributeValue::findOrFail($attributeValue);
                        $product->name .= ' '.$loadedAttributeValue->name;
                        $product->sku .= ' '.$loadedAttributeValue->id;

                        $toSyncAttributeValues[$attributeValue] = [
                            'product_attribute_id' => $loadedAttributeValue->product_attribute_id
                        ];
                    }

                    $product->parent()->associate($parentProduct);

                    //Update manufacturer
                    $product->manufacturer_id = $parentProduct->manufacturer_id;

                    $product->save();

                    //Update children product categories
                    $product->categories()->sync($parentProduct->categories->pluck('id')->all());

                    //Update children features
                    $features = [];

                    foreach($parentProduct->productFeatureValues as $parentProductFeature){
                        $features[$parentProductFeature->id] = [
                            'product_feature_id' => $parentProductFeature->pivot->product_feature_id
                        ];
                    }

                    $product->productFeatureValues()->sync($features);

                    $product->productAttributeValues()->sync($toSyncAttributeValues);

                    $images = [];

                    if($request->has('variation.images')){
                        foreach($request->input('variation.images', []) as $idx=>$image){
                            $images[$image] = [
                                'type' => 'image',
                                'locale' => $product->getTranslation()->locale
                            ];
                        }
                    }

                    $product->getTranslation()->syncMedia($images, 'image');

                    $thumbnails = [];

                    if($request->has('variation.thumbnails')){
                        foreach($request->input('variation.thumbnails', []) as $idx=>$image){
                            $thumbnails[$image] = [
                                'type' => 'thumbnail',
                                'locale' => $product->getTranslation()->locale
                            ];
                        }
                    }

                    $product->getTranslation()->syncMedia($thumbnails, 'thumbnail');

                    $productDetail = $product->productDetail;

                    if(!$productDetail->exists){
                        $productDetail->product()->associate($product);
                    }

                    $productDetail->fill($request->input('variation.productDetail'));
                    $productDetail->store_id = $request->input('variation.store_id');
                    $productDetail->taxable = $parentProduct->productDetail->taxable;
                    $productDetail->save();
                }
            }
        }

        return response()->json([
            'message' => 'Variations have been created.',
            '_token' => csrf_token(),
            'result' => 'success'
        ]);
    }

    public function featureIndex(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $newFeatures = $request->input('new_features', []);

        $selectedFeatures = $request->input('features', []);

        if($request->has('remove_feature') && isset($selectedFeatures[$request->input('remove_feature')])){
            unset($selectedFeatures[$request->input('remove_feature')]);
        }else{
            $selectedFeatures += array_flip($newFeatures);
        }

        $allFeatures = ProductFeature::withTranslation()->get();

        $featureOptions = [];
        foreach($allFeatures as $allFeature){
            if(!isset($selectedFeatures[$allFeature->id])){
                $featureOptions[$allFeature->id] = $allFeature->name;
            }
        }

        $features = [];
        foreach($selectedFeatures as $selectedFeature => $selectedFeatureValue){
            $features[] = ProductFeature::findOrFail($selectedFeature);
        }

        Session::flashInput([
            'features' => $selectedFeatures
        ]);

        $return = view('backend.catalog.product.product_feature_index', [
            'product' => $product,
            'featureOptions' => $featureOptions,
            'features' => $features
        ])->render();

        return response()->json([
            'html' => $return
        ]);
    }

    public function autocomplete(Request $request)
    {
        $return = [];
        $search = $request->get('query', '');

        if(!empty($search)){
            $qb = Product::with('parent')->joinTranslation()->joinDetail()->selectSelf();

            if($request->input('entity_only') == '1'){
                $qb->productEntity();
            }else{
                $qb->productSelection();
            }

            if($request->input('exclude', null)){
                $qb->where('products.id', '<>', $request->input('exclude'));
            }

            if(!$request->user()->can('access', ['add_inactive_product'])){
                $qb->where('D.active', 1);
            }

            if(!$request->user()->can('access', ['add_unavailable_product'])){
                $qb->where('D.available', 1);
            }

            $qb->where(function($query) use ($search){
                $query->where('name', 'LIKE', '%'.$search.'%')
                    ->orWhere('sku', 'LIKE', '%'.$search.'%');
            });

            $results = $qb->get();

            foreach($results as $result){
                $name = $result->name.' ('.$result->sku.')';

                if($result->parent && $result->parent->defaultCategory){
                    $name .= ' - '.$result->parent->defaultCategory->name;
                }elseif($result->defaultCategory){
                    $name .= ' - '.$result->defaultCategory->name;
                }

                $return[] = [
                    'id' => $result->id,
                    'name' => $name,
                    'sku' => $result->sku,
                    'thumbnail' => $result->hasThumbnail()?asset($result->getThumbnail()->getImagePath('backend_thumbnail')):'',
                    'tokens' => [
                        $name,
                        $result->sku
                    ]
                ];
            }
        }

        return response()->json(['data' => $return, '_token' => csrf_token()]);
    }

    public function getRelatedProduct($id, $type)
    {
        $product = Product::findOrFail($id);

        $return = view('backend.catalog.product.product_relation_result', [
            'product' => $product,
            'relation' => $type
        ])->render();

        return new JsonResponse([
            'data' => $return,
            '_token' => csrf_token()
        ]);
    }

    public function availability(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $store = ProjectHelper::getStoreByRequest($request);
        $store_id = $store->id;

        $options = [
            'store' => $store_id,
            'date' => Carbon::now()->format('Y-m-d'),
            'delivery_date' => $request->input('delivery_date', null)
        ];
        $orderLimit = $product->getOrderLimit($options);

        $stock = $product->getStock();

        if(isset($orderLimit['limit_type']) && $orderLimit['limit_type'] == 'delivery_date'){
            $totalOrderedByDelivery = 0;

            if($options['delivery_date']){
                $totalOrderedByDelivery = $product->getOrderCount([
                    'delivery_date' => $options['delivery_date'],
                    'store' => $store_id
                ]);
            }

            $totalOrdered = $totalOrderedByDelivery;
        }else{
            $totalOrderedByDate = $product->getOrderCount([
                'checkout_at' => $options['date'],
                'store' => $store_id
            ]);

            $totalOrdered = $totalOrderedByDate;
        }

        $return = [
            'ordered_total' => $totalOrdered,
            'order_limit' => is_null($orderLimit)?$orderLimit:$orderLimit['limit'],
            'stock' => $stock
        ];

        return new JsonResponse([
            'data' => $return,
            '_token' => csrf_token()
        ]);
    }

    public function availabilityCalendar(Request $request)
    {
        //Form months array
        $month = $request->input('month');
        $year = $request->input('year');
        $internal = $request->input('internal', 0);

        $focusedDate = Carbon::createFromFormat('Y-n', $year.'-'.$month);

        $months[] = $focusedDate->format('n-Y');

        $products = [];
        $disabledDates = [];
        $orderedQuantities = [];

        if($internal){
            $order = Order::find($request->input('order_id', null));

            foreach($request->input('line_items', []) as $idx=>$lineItemDatum) {
                if ($lineItemDatum['line_item_type'] == 'product' && empty($lineItemDatum['quantity'])) {
                    continue;
                }elseif($lineItemDatum['line_item_type'] == 'product' && !empty($lineItemDatum['line_item_id'])){
                    $products[$idx] = Product::findOrFail($lineItemDatum['line_item_id']);
                    $orderedQuantities[$idx] = $request->input('line_items.'.$idx.'.quantity');
                }
            }

            $store = ProjectHelper::getStoreByRequest($request);
        }else{
            $focusedDate->modify('+1 week');
            $months[] = $focusedDate->format('n-Y');

            $order = FrontendHelper::getCurrentOrder();

            foreach($order->getProductLineItems() as $idx => $productLineItem) {
                $products[$idx] = $productLineItem->product;
                $orderedQuantities[$idx] = $productLineItem->quantity;
            }

            $store = ProjectHelper::getStoreByRequest($request, $order->store);
        }

        $store_id = $store->id;

        foreach($products as $idx=>$product){
            $orderedQuantity = $orderedQuantities[$idx];

            $options = [
                'store' => $store_id,
                'quantity' => $orderedQuantity,
                'months' => $months,
                'format' => 'Y-n-j',
                'saved_quantity' => ($order && $order->isCheckout)?$order->getProductQuantity($product->id):0,
                'saved_delivery_date' => ($order && $order->delivery_date)?$order->delivery_date->format('j-n-Y'):null
            ];

            $disabledDates += $product->getUnavailableDeliveryDates($options);
        }

        return new JsonResponse([
            'disabled_dates' => array_unique($disabledDates),
            '_token' => csrf_token()
        ]);
    }

    public function getViewSuggestions()
    {
        $viewSuggestions = [];

        $viewSuggestions += ['frontend.catalog.product_category.view_'.$this->id, 'frontend.catalog.product_category.view'];

        return $viewSuggestions;
    }

    protected function deleteable($id)
    {
        $orderCount = Order::checkout()->whereHasLineItem($id, 'product')->count();

        return $orderCount < 1;
    }

    protected function getCombinationsFromArray($arrays, $i = 0) {
        $result = array(array());
        foreach ($arrays as $property => $property_values) {
            $tmp = array();
            foreach ($result as $result_item) {
                foreach ($property_values as $property_key => $property_value) {
                    $tmp[] = $result_item + array($property_key => $property_value);
                }
            }
            $result = $tmp;
        }
        return $result;
    }

    private function _searchAttributeId($array, $value)
    {
        foreach($array as $idx => $arrayItem){
            if(array_search($value, $arrayItem) !== false){
                return $idx;
            }
        }

        return null;
    }
}