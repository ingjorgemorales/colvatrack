<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ToolRequest;
use App\Services\ToolRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
class ToolRequestController extends Controller
{
    public function store(Request $request, ToolRequestService $service){ abort_unless($request->user()->hasRole('Tecnico','Administrador'),403); $data=$request->validate(['vehicle_id'=>['required','exists:vehicles,id'],'driver_id'=>['nullable','exists:users,id'],'technician_latitude'=>['required','numeric'],'technician_longitude'=>['required','numeric'],'technician_address'=>['nullable','string'],'priority'=>['required','in:baja,normal,alta,critica'],'observation'=>['nullable','string'],'items'=>['required','array','min:1'],'items.*.inventory_item_id'=>['required',Rule::exists('inventory_items','id')->where('status','active')],'items.*.quantity'=>['required','integer','min:1']]); $data['technician_id']=$request->user()->id; try { $toolRequest = $service->create($data); } catch (InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); } return response()->json($toolRequest->load('items.item','vehicle','driver'),201); }
    public function status(Request $request, ToolRequest $toolRequest, ToolRequestService $service){ $data=$request->validate(['status'=>['required','in:pendiente,aceptada,rechazada,en_camino,entregada,en_uso,para_recoger,recogida,finalizada,cancelada'],'comment'=>['nullable','string']]); return $service->changeStatus($toolRequest,$data['status'],$request->user()->id,$data['comment'] ?? null); }
}
