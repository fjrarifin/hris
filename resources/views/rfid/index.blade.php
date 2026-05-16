@extends('layouts.app')

@section('title', 'RFID Tags')

@section('content')
	<div class="card">
		<div class="card-header">
			<h3 class="card-title">Recent RFID Scans</h3>
		</div>
		<div class="card-body">
			<table id="rfid-table" class="table-bordered table-striped table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Tag</th>
						<th>User</th>
						<th>Scanned At</th>
						<th>Aksi</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($tags as $tag)
						<tr>
							<td>{{ $tag->id }}</td>
							<td>{{ $tag->tag }}</td>
							<td>
								@if ($tag->user)
									{{ $tag->user->name }}
								@else
									<span class="text-muted">(unassigned)</span>
								@endif
							</td>
							<td>{{ $tag->created_at }}</td>
							<td>
								@if (!$tag->user)
									<form method="POST" action="{{ route('rfid.assign', $tag) }}">
										@csrf
										<div class="input-group input-group-sm">
											<select name="user_id" class="form-control">
												<option value="">--select user--</option>
												@foreach ($users as $u)
													<option value="{{ $u->id }}">{{ $u->name }}</option>
												@endforeach
											</select>
											<div class="input-group-append">
												<button class="btn btn-primary">Link</button>
											</div>
										</div>
									</form>
								@endif
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
@endsection

@section('scripts')
	<script>
		$(function() {
			$('#rfid-table').DataTable({
				"order": [
					[0, "desc"]
				]
			});
		});
	</script>
@endsection
