import 'dart:async';
import 'package:flutter/material.dart';
import '../session.dart';
import '../widgets/common.dart';

class TripsScreen extends StatefulWidget {
  final Session session;
  const TripsScreen({super.key, required this.session});
  @override
  State<TripsScreen> createState() => _TripsScreenState();
}
class _TripsScreenState extends State<TripsScreen> with WidgetsBindingObserver {
  Timer? timer;
  bool foreground = true;
  List trips = [];
  String tab = 'new';
  int page = 1, last = 1, version = 0;
  bool loading = true, busy = false;
  String? error;
  @override
  void initState() {
    super.initState(); WidgetsBinding.instance.addObserver(this); load();
    timer = Timer.periodic(const Duration(seconds:30), (_) { if (foreground && !busy && !loading) load(silent:true); });
  }
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) { foreground = state == AppLifecycleState.resumed; if(foreground && !busy) load(silent:true); }
  @override
  void dispose() { timer?.cancel(); WidgetsBinding.instance.removeObserver(this); super.dispose(); }
  Future<void> load({bool silent = false}) async {
    final attempt = ++version;
    setState(() { if(!silent) loading = true; error = null; });
    try {
      final result = await widget.session.api.get('/api/rider/trips',{'tab':tab,'page':'$page'});
      if(mounted && attempt == version) setState(() { trips = List.from(result['data'] ?? []); last = int.tryParse('${result['last_page']}') ?? 1; });
    } catch(e) { if(mounted && attempt == version) setState(() => error = '$e'); }
    finally { if(mounted && attempt == version) setState(() => loading = false); }
  }
  Future<void> action(String path, String label, {Map<String,dynamic> fields = const {}}) async {
    if(busy) return;
    final yes = await showDialog<bool>(context: context,builder: (ctx) => AlertDialog(title: Text('$label?'),
      content: const Text('This updates the trip in LokaShop Logistics.'),actions: [
        TextButton(onPressed: () => Navigator.pop(ctx,false),child: const Text('Go back')),
        FilledButton(onPressed: () => Navigator.pop(ctx,true),child: Text(label)),
      ]));
    if(yes != true || !mounted) return;
    setState(() => busy = true);
    try { await widget.session.api.post(path,fields); tab = 'progress'; page = 1; await load(); }
    catch(e) { if(mounted) toast(context,e); }
    finally { if(mounted) setState(() => busy = false); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('My transport trips'),actions: [IconButton(onPressed: busy?null:load,tooltip: 'Refresh trips',icon: const Icon(Icons.refresh))]),
    body: Column(children: [
      Padding(padding: const EdgeInsets.all(16),child: SizedBox(width: double.infinity,child: SegmentedButton<String>(
        segments: const [ButtonSegment(value:'new',label:Text('Offers')),ButtonSegment(value:'progress',label:Text('In progress')),ButtonSegment(value:'completed',label:Text('Completed'))],
        selected:{tab},onSelectionChanged: busy?null:(values) { tab=values.first;page=1;load(); },
      ))),
      Expanded(child: loading ? const Center(child:CircularProgressIndicator()) : error != null ? Center(child:Padding(padding:const EdgeInsets.all(24),child:Text(error!))) : RefreshIndicator(onRefresh:load,child:ListView(
        physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.fromLTRB(18,0,18,24),children:[
          if(trips.isEmpty) const Padding(padding:EdgeInsets.all(32),child:Column(children:[Icon(Icons.local_shipping_outlined,size:48),SizedBox(height:16),Text('No trips in this section yet.')])),
          ...trips.map((trip) => Card(margin:const EdgeInsets.only(bottom:16),child:Padding(padding:const EdgeInsets.all(20),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
            Text('Trip #${trip['id']}',style:const TextStyle(fontSize:22,fontWeight:FontWeight.w800)),
            const SizedBox(height:8),Chip(label:Text('${trip['status']}'.replaceAll('_',' '))),
            Text('${(trip['stops'] as List? ?? []).length} destination sorting centers'),
            if(trip['status']=='offered') Wrap(spacing:8,children:[
              FilledButton(onPressed:busy?null:()=>action('/api/rider/trips/${trip['id']}/accept','Accept whole trip'),child:const Text('Accept trip')),
              OutlinedButton(onPressed:busy?null:()=>action('/api/rider/trips/${trip['id']}/decline','Decline trip'),child:const Text('Decline')),
            ]),
            if(trip['status']=='accepted') FilledButton(onPressed:busy?null:()=>action('/api/rider/trips/${trip['id']}/depart','Start transport'),child:const Text('Start transport')),
            ...(trip['stops'] as List? ?? []).map((stop) => Padding(padding:const EdgeInsets.only(top:16),child:Container(
              padding:const EdgeInsets.all(16),decoration:BoxDecoration(color:const Color(0xFFF0F5EF),borderRadius:BorderRadius.circular(16)),
              child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
                Text('${stop['center_name']}',style:const TextStyle(fontWeight:FontWeight.w800)),Text('${stop['city']}, ${stop['province']}'),
                if(stop['address'] is Map) Text('${stop['address']['street']}, ${stop['address']['barangay']}\n${stop['address']['phone']}'),
                const Divider(),
                ...(stop['parcels'] as List? ?? []).map((p) => Padding(padding:const EdgeInsets.symmetric(vertical:3),child:Text('${p['tracking_code']} · ${p['received_at'] != null ? 'Received' : 'Awaiting receipt'}'))),
                const SizedBox(height:10),
                if(stop['received_at'] != null) const Text('Destination center confirmed receipt.',style:TextStyle(fontWeight:FontWeight.w700))
                else if(stop['handed_over_at'] != null) const Text('Handover recorded. Waiting for center receipt.')
                else if(trip['status']=='in_transit') FilledButton.icon(
                  onPressed:busy?null:()=>action('/api/rider/trip-stops/${stop['id']}/handover','Confirm handover',fields:{'confirm_handover':true}),
                  icon:const Icon(Icons.handshake_outlined),label:const Text('Confirm handover')),
              ]),
            ))),
          ])))),
          if(last>1) Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[
            TextButton(onPressed:page>1&&!busy?(){page--;load();}:null,child:const Text('Previous')),
            Text('$page / $last'),TextButton(onPressed:page<last&&!busy?(){page++;load();}:null,child:const Text('Next')),
          ]),
        ],
      ))),
    ]),
  );
}
