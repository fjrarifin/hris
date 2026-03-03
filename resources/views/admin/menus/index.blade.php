@extends('layouts.app')

@section('content')
	<div class="card">
		<div class="card-header d-flex justify-content-between">
			<h3 class="card-title">Menu Management</h3>
			<a href="{{ route('admin.menus.create') }}" class="btn btn-primary btn-sm">
				+ Tambah Menu
			</a>
		</div>

		<div class="card-body">
			<table class="table-bordered table">
				<thead>
					<tr>
						<th>Nama</th>
						<th>Route</th>
						<th>Permission</th>
						<th>Icon</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($menus as $menu)
						<tr>
							<td><strong>{{ $menu->name }}</strong></td>
							<td>{{ $menu->route }}</td>
							<td>{{ $menu->permission_key }}</td>
							<td><i class="{{ $menu->icon }}"></i></td>
						</tr>

						@foreach ($menu->children as $child)
							<tr>
								<td class="pl-4">↳ {{ $child->name }}</td>
								<td>{{ $child->route }}</td>
								<td>{{ $child->permission_key }}</td>
								<td><i class="{{ $child->icon }}"></i></td>
							</tr>
						@endforeach
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
@endsection
