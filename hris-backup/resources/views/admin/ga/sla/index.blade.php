@extends('layouts.app')

@section('title', 'SLA Performance')
@section('page_title', 'SLA Performance')
@section('page_desc', 'Monitoring SLA vendor & fasilitas')

@section('content')
	<div class="rounded-3xl border bg-white p-8 shadow-sm">
		<h2 class="text-lg font-bold">📈 SLA Performance</h2>

		<table class="mt-4 w-full text-sm">
			<thead class="border-b text-left text-gray-500">
				<tr>
					<th class="py-2">Vendor</th>
					<th>Target</th>
					<th>Realisasi</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<tr class="border-b">
					<td class="py-2">Cleaning Service</td>
					<td>95%</td>
					<td>97%</td>
					<td class="font-semibold text-green-600">OK</td>
				</tr>
			</tbody>
		</table>
	</div>
@endsection
