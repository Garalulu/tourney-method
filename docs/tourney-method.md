# Tourney method
## 개요
2022년부터 3년간 외국어 포럼 형식과 영어가 어색한 한국인들을 위해서 Google Sheet를 이용해 osu! 사이트 Tournaments 포럼에서 신규 스탠다드 모드 토너먼트 정보를 손으로 정리해 한 눈에 볼 수 있게 만들었고, 또 한국인을 위한 디스코드 서버 osu! Korean Tourney Hub을 개설했다. 서버에는 랭크별로 역할이 주어져 있는데, Open Rank/100+/500+/1,000+/5,000+/10,000+으로 나누어 신규 토너먼트가 나올 때마다 역할 핑과 함께 홍보를 했다. 또 매 주 월요일마다 지난 주말에 마무리된 뱃지 토너먼트와 한국인 입상 목록을 정리해 같이 축하하기도 했다. 한국인이 참여한 경기가 진행 중일때는 방송을 홍보했고, 특히 뱃지 결정전인 Grand Final에서는 전원 핑을 찍어 다같이 응원하였다.
이 과정은 주마다 꽤 많은 시간을 소모하게 했으며, 이를 자동화할 수 있는 방법은 없을까 하는 마음에 이 프로젝트를 시작한다.
또 더 나아가 모든 모드 및 전 세계 사람들이 사용할 수 있는 사이트를 만들고 싶다.

## 기능
### 신규 토너먼트 파싱 with osu! api v2
- osu! api v2 GET /forums/topics를 이용해 포럼 토픽 불러오기
- forum_id=55
- 최대 50개의 토픽을 불러올 수 있다
- osu! api v2 GET /forums/topics/{topic}을 이용해 토픽 내용 불러오기
- 첫 게시글만 불러오면 됨. 
- 파싱할 내용
```
제목: 토너먼트 제목. 보통 토픽 제목에 명시되어 있음
호스트: 토너먼트 호스트. Default=토픽 작성자. 보통 토픽 하단 스태프 명단에 Host 옆에 명시되어 있음
토너먼트 모드: Standard, Taiko, Catch, Mania. 여러 모드를 함께 진행하는 Multimode인 경우도 있음
토너먼트 배너: 토픽 맨 처음 이미지
메인 시트: Main Sheet에 연결된 Google Sheet 링크
디스코드 서버: discord 서버 초대 링크
방송 링크: twitch 또는 Youtube 링크
대진표: challonge 링크
참가 신청: Player Reg에 연결된 Google Form 링크
포럼 링크: https://osu.ppy.sh/community/forums/topics/{토픽 ID}?n=1
뱃지: 1등 보상에 Badge가 있거나 토픽 하단에 https://tcomm.hivie.tn/reports/create or https://pif.ephemeral.ink/tournament-reports 가 있는 경우
랭크 범위: 토너먼트에 참가할 수 있는 랭크 범위. Default=Open Rank. 보통 토픽 제목에 명시되어 있음
BWS: osu! 스탠다드 모드 토너먼트에서만 쓰는 특별한 계산식. `rank^((0.9937^((badges^2)/1)))`. 토너먼트마다 커스텀 BWS 공식을 쓰는 경우도 있음. 만약 BWS를 사용한다면, 기본 랭크에 이 계산식을 넣었을 때 랭크 범위 안에 들어야 함
vs: 토너먼트 경기 사이즈. 1v1, 2v2, 3v3, 4v4가 일반적으로 사용됨. 배틀 로얄 형식으로 한 로비에 8~16명이 모여 한 맵마다 꼴찌를 탈락시키는 FFA 형식도 있음.
팀 사이즈: 한 팀 가용 인원. 보통 vs 인원이 최소, 최대 8명까지 한 팀에 일반적으로 들어감. 대회를 진행하면서 팀 사이즈가 달라지는 경우도 있음.
신청 기간: Default=토픽 생성 시간부터 Signup/Registration 마지막 날짜까지. 시작 날짜가 명시되어있다면 시작 날짜 사용
토너 종료: Grand Finals 마지막 날짜
SR: 오스 토너먼트는 라운드마다 점점 어려워지는 난이도의 맵풀을 사용하여 경기한다. 시작 라운드 NM1 기준 Star Rating(SR), Grand Final SR, Qualifiers SR(없으면 생략)을 가져와야 함.
토너먼트 형식: Double Elimination, Single Elimination, Swiss. 토너먼트 진행 형식
토너먼트 특징: Suiji(Random Team), Draft, Battle Royale, World Cup, Regional(지역 제한)
```
- 하루에 한 번 18:00 UTC+9에 파싱을 진행.

### 예전 토너먼트 파싱 및 종료된 토너먼트 뱃지/승자 가져오기
- osu! api v2로는 최근 50개 토픽만 볼 수 있기 때문에 예전 토너먼트를 불러올 수 없음
- https://github.com/Hiviexd/tournament-tracker 에서 예전 토너먼트 불러오기
- GET /api/tournaments — queries the tournament listing
- GET /api/tournaments/:id — gets a specific tournament via its ID
- https://raw.githubusercontent.com/Hiviexd/tournament-tracker/refs/heads/main/interfaces/Tournament.ts 참조
- 모든 예전 종료된 토너먼트를 한 번만 불러오고, 이후에는 개별 토너만 후술된 시간에 파싱할 것
- 토너먼트 승자, 뱃지 디자인도 불러올 수 있음. 각 토너 종료 2주 후부터 1주에 한 번 18:00 UTC+9에 파싱 진행

## 페이지 구성

### 토너먼트 승인/편집 어드민 페이지
- 아무래도 LLM을 사용하지 않는 파싱 방식이다 보니 실제 정보와 다를 수 있음
- 어드민들이 파싱된 토너먼트 내용을 검토 후 이상 없으면 승인
- 승인한 토너먼트만 public에 공개됨

### 메인 페이지
- 모드 선택용 아이콘. 스탠다드, 태고, 캐치, 매니아. 디폴트는 스탠다드고, 특정 모드 클릭시 해당하는 모드의 내용만 보여줌.
- 현재 진행중인 토너먼트(종료일이 가까운 순), 참가 신청중인 토너먼트(신규 순)를 각각 3개까지 표시
- 100등, 1000등, 10000등(캐치는 5000등)의 현재 pp 표시
- 마지막 업데이트 시간 표시
- Community Tournament Status Tracker 링크(https://tcomm.hivie.tn/tournaments?mode=osu&type=tournament&state=active)
- 올해 뱃지 토너먼트 갯수 표시
- 로그인은 osu! OAuth2.0으로 진행

### 토너먼트 리스트 페이지
- 토너먼트 검색, 리스트 갯수, 필터(뱃지, 랭크 범위) 등을 지원해야 함
- 참가할 수 있는 토너먼트 보기 체크표시를 하면 현재 참가 신청중인 토너먼트 중 본인 랭크 범위에 맞는 토너먼트만 보여줌
- 종료된 토너먼트 리스트로 가면 대회 종료일 기준으로 1년 단위로 확인 가능

## 개발 스택
- 간단한 1인 개발 프로젝트이기 때문에, 기술 스택 간소화
- Vanilla PHP + jQuery + Vanilla CSS
- 데이터베이스는 SQLite로 모든 데이터를 서버의 단일 파일로 저장
- 호스팅: DigitalOcean

### 미니멀리스트 기술 스택
- 경쟁력 1: 속도와 빠른 프로토타이핑
- 경쟁력 2: 단순함과 통제
- 경쟁력 3: 고객 가치에 집중
- 논평: 완벽보다 실용

## 차후 추가 기능 계획

### 유저 개인 페이지
- 로그인 후 본인이 참여한 토너 명단 확인
- 매치 기록 확인
- 연말 정산으로 매년 참가한 토너 기록을 이미지 형태로 공유하기

### 참가신청 구글 폼 대체용 페이지 + 디스코드 봇 연동
- 대회와 연동하여 사이트에서 오스 로그인, 디스코드 로그인 후 참가 신청할 수 있는 페이지 제작
- 디스코드 봇이 사용자가 서버에 들어왔을 경우에만 최종적으로 참가 신청 허용
- 참가 신청 완료시 연결된 시트에 명단 보내기, 사용자가 디스코드 서버에 들어왔을 경우 자동으로 Player 역할 부여
- 차후 80% 이상 열리는 대회가 이를 사용해 구글 폼을 대체하는게 목표
- 이를 사용하면 특정 토너먼트에 참가한 명단을 구글 시트에서 파싱하는 것보다 수월하게 정리할 수 있음
