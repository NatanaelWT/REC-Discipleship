<?php

function public_member_feedback_questions(): array {
    return [
        'leadership' => [
            'title' => 'Kepemimpinan',
            'intro' => 'Isian ini menolong pemimpin DG mengevaluasi dan meningkatkan pelayanan kepada kelompok.',
            'ratings' => [
                ['key' => 'leader_facilitator', 'label' => 'Apakah pemimpin DG dapat menjadi fasilitator yang baik?', 'scale' => 10, 'left' => 'Sangat buruk', 'right' => 'Sangat baik'],
                ['key' => 'leader_preparation', 'label' => 'Apakah pemimpin DG mempersiapkan tiap sesi dengan baik?', 'scale' => 10, 'left' => 'Sangat buruk', 'right' => 'Sangat baik'],
                ['key' => 'leader_attention', 'label' => 'Apakah pemimpin DG sudah memberikan perhatian yang cukup pada setiap anggota?', 'scale' => 10, 'left' => 'Sangat kurang', 'right' => 'Sangat cukup'],
            ],
            'note_key' => 'leader_notes',
            'note_label' => 'Hal lain apa yang bisa Saudara bagikan mengenai pemimpin DG Saudara?',
        ],
        'meeting' => [
            'title' => 'Teknis Pertemuan (Bertemu)',
            'ratings' => [
                ['key' => 'meeting_place', 'label' => 'Apakah tempat yang dipilih untuk pertemuan DG sesuai?', 'scale' => 10, 'left' => 'Sangat tidak sesuai', 'right' => 'Sangat sesuai'],
                ['key' => 'meeting_frequency', 'label' => 'Apakah frekuensi pertemuan yang disepakati sudah dijalankan sesuai?', 'scale' => 10, 'left' => 'Sangat tidak sesuai', 'right' => 'Sangat sesuai'],
                ['key' => 'meeting_duration', 'label' => 'Bagaimana dengan durasi tiap sesi DG yang dijalani?', 'scale' => 5, 'left' => 'Sangat pendek', 'right' => 'Sangat panjang'],
                ['key' => 'meeting_member_count', 'label' => 'Apakah jumlah anggota DG sudah sesuai menurut Saudara?', 'scale' => 5, 'left' => 'Terlalu sedikit', 'middle' => '3 = cukup/sesuai', 'right' => 'Terlalu banyak'],
            ],
            'note_key' => 'meeting_notes',
            'note_label' => 'Hal lain apa yang bisa Saudara bagikan mengenai teknis pertemuan DG Saudara?',
        ],
        'teaching' => [
            'title' => 'Pengajaran (Belajar)',
            'ratings' => [
                ['key' => 'teaching_discussion', 'label' => 'Apakah dalam pertemuan, materi DG sudah dibahas dan didiskusikan secara cukup?', 'scale' => 10, 'left' => 'Sangat kurang', 'right' => 'Sangat cukup'],
                ['key' => 'teaching_gospel_understanding', 'label' => 'Apakah materi DG yang dibahas membuat Saudara memahami pentingnya menjadikan Injil sebagai pusat hidup?', 'scale' => 10, 'left' => 'Tidak paham pentingnya Injil', 'right' => 'Sangat paham'],
                ['key' => 'teaching_gospel_motivation', 'label' => 'Apakah materi DG yang dibahas memotivasi Saudara untuk bertumbuh dan menjadikan Injil sebagai pusat hidup Saudara?', 'scale' => 10, 'left' => 'Tidak termotivasi', 'right' => 'Sangat termotivasi'],
            ],
            'note_key' => 'teaching_notes',
            'note_label' => 'Hal lain apa yang bisa Saudara bagikan mengenai pembahasan materi di DG Saudara?',
        ],
        'personal_growth' => [
            'title' => 'Pertumbuhan Pribadi (Berbagi Hidup)',
            'ratings' => [
                ['key' => 'growth_commitment', 'label' => 'Seberapa jauh pertemuan DG membuat Saudara berkomitmen untuk semakin bertumbuh dan menjadikan Injil sebagai pusat hidup Saudara?', 'scale' => 10, 'left' => 'Pertemuan DG tidak/belum mendorong komitmen itu', 'right' => 'Pertemuan DG sangat membantu'],
                ['key' => 'growth_personal_openness', 'label' => 'Seberapa Saudara terbuka dalam membagikan kisah hidup personal dan diri yang otentik kepada sesama anggota DG Saudara?', 'scale' => 10, 'left' => 'Tidak/belum bisa terbuka', 'right' => 'Sangat terbuka'],
            ],
            'note_key' => 'growth_notes',
            'note_label' => 'Hal lain apa yang bisa Saudara bagikan mengenai pertumbuhan pribadi melalui kelompok DG Saudara?',
        ],
        'relationships' => [
            'title' => 'Relasi Anggota (Bertolong-tolongan)',
            'ratings' => [
                ['key' => 'relation_knowing', 'label' => 'Seberapa jauh anggota kelompok DG Saudara mengenal satu sama lain?', 'scale' => 10, 'left' => 'Kami belum benar-benar saling mengenal', 'right' => 'Kami sudah saling mengenal dengan sangat baik'],
                ['key' => 'relation_openness', 'label' => 'Seberapa besar suasana keterbukaan yang ada antar anggota dalam DG Saudara?', 'scale' => 10, 'left' => 'Tidak/belum ada suasana keterbukaan', 'right' => 'Sudah ada suasana keterbukaan'],
                ['key' => 'relation_spiritual_support', 'label' => 'Seberapa besar suasana saling mendukung pertumbuhan rohani antar anggota dalam DG Saudara?', 'scale' => 10, 'left' => 'Tidak/belum ada suasana demikian', 'right' => 'Sudah ada suasana demikian'],
                ['key' => 'relation_mutual_help', 'label' => 'Seberapa besar suasana saling tolong-menolong (memberi & menerima) yang ada, baik dalam pertemuan maupun dalam keseharian?', 'scale' => 10, 'left' => 'Sangat kecil', 'right' => 'Sangat besar'],
            ],
            'note_key' => 'relationship_notes',
            'note_label' => 'Hal lain apa yang bisa Saudara bagikan mengenai relasi antar anggota dalam DG Saudara?',
        ],
    ];
}
