<?php
declare(strict_types=1);

namespace SmartAlloc\REST\Controllers;

use SmartAlloc\RuleEngine\RuleEngineException;
use SmartAlloc\RuleEngine\RuleEngineService;

final class RuleEngineController{
    public function register(): void{
        $cb=function(): void{
            register_rest_route('smartalloc/v1','/rule-engine/evaluate',[
                'methods'=>'POST',
                'permission_callback'=>static fn(): bool=>current_user_can(SMARTALLOC_CAP),
                'callback'=>function(\WP_REST_Request $r){
                    if(!current_user_can(SMARTALLOC_CAP)) return new \WP_REST_Response(['error'=>'forbidden'],403);
                    $n=(string)$r->get_header('X-WP-Nonce');
                    if(!wp_verify_nonce($n,'smartalloc_rest')) return new \WP_REST_Response(['error'=>'forbidden'],403);
                    try{
                        $p=$r->get_json_params();
                        if(isset($p['entry_id'])){absint($p['entry_id']); $ctx=['school_fuzzy'=>0.85];}
                        else{$ctx=(array)($p['payload']??[]);}
                        return rest_ensure_response((new RuleEngineService())->evaluate($ctx));
                    }catch(RuleEngineException $e){
                        return new \WP_REST_Response(['error'=>$e->getMessage()],400);
                    }
                },
                'args'=>[
                    'entry_id'=>['type'=>'integer','minimum'=>1],
                    'payload'=>['type'=>'object'],
                ],
            ]);
        };
        add_action('rest_api_init',$cb);
        if(defined('PHPUNIT_RUNNING')&&PHPUNIT_RUNNING){$cb();}
    }
}
